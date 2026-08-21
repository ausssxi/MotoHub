<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Services\Bike\PriceStatsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

final class ShortcodeService
{
    public function __construct(
        private readonly PriceStatsService $priceStats,
    ) {}

    /**
     * HTML内のショートコードを検出して置換する。
     *  - [riders-map ...]      … 地図埋め込み
     *  - [bikes <id|mfr/slug> …] … 関連車種の在庫CTAブロック（記事末などに著者が明示配置）
     *
     * @return array{html: string, hasMap: bool}
     */
    public function processShortcodes(string $html): array
    {
        $hasMap = false;

        // CommonMarkが<p>タグで囲む場合と囲まない場合の両方に対応
        $pattern = '/(?:<p>\s*)?\[riders-map\s+([^\]]+)\](?:\s*<\/p>)?/';

        $html = preg_replace_callback($pattern, function (array $matches) use (&$hasMap) {
            $hasMap = true;
            $params = $this->parseParams($matches[1]);

            $lat = $params['lat'] ?? '35.681236';
            $lng = $params['lng'] ?? '139.767125';
            $zoom = $params['zoom'] ?? '12';
            $height = $params['height'] ?? '400';
            $layers = $params['layers'] ?? 'all';
            $route = $params['route'] ?? '';

            $attrs = sprintf(
                'data-lat="%s" data-lng="%s" data-zoom="%s" data-layers="%s"',
                e($lat),
                e($lng),
                e($zoom),
                e($layers),
            );

            if ($route !== '') {
                $attrs .= sprintf(' data-route="%s"', e($route));
            }

            return sprintf(
                '<div class="riders-map-embed" %s style="height:%dpx;width:100%%;min-height:300px;border-radius:0.75rem;overflow:hidden;margin:1.5rem 0;"></div>',
                $attrs,
                max((int) $height, 300),
            );
        }, $html) ?? $html;

        // [bikes honda/super-cub-110 kawasaki/gpz900r 629] → 在庫CTAブロック
        $bikesPattern = '/(?:<p>\s*)?\[bikes\s+([^\]]+)\](?:\s*<\/p>)?/u';
        $html = preg_replace_callback($bikesPattern, function (array $matches): string {
            return $this->renderBikesBlock($matches[1]);
        }, $html) ?? $html;

        // [chain-shops slug="red-baron"] → チェーン店舗の都道府県別リンク一覧
        $chainShopsPattern = '/(?:<p>\s*)?\[chain-shops\s+([^\]]+)\](?:\s*<\/p>)?/u';
        $html = preg_replace_callback($chainShopsPattern, function (array $matches): string {
            return $this->renderChainShopsBlock($matches[1]);
        }, $html) ?? $html;

        return ['html' => $html, 'hasMap' => $hasMap];
    }

    /**
     * [chain-shops slug="..."] を、config('bike.chains') の対象チェーン店舗の
     * 都道府県別リンク一覧（/shops/{id} への導線）に展開する。
     * 未知slug・該当0件は空文字（エラーを投げない）。レッドバロン専用にせず任意チェーンで動作。
     */
    private function renderChainShopsBlock(string $rawParams): string
    {
        $params = $this->parseParams($rawParams);
        $slug = $params['slug'] ?? '';
        if ($slug === '') {
            Log::warning('[chain-shops] slug 未指定のため空出力', ['slug' => '']);

            return '';
        }

        $chain = config("bike.chains.{$slug}");
        if (! is_array($chain)) {
            Log::warning('[chain-shops] 未知の slug のため空出力: '.$slug, ['slug' => $slug]);

            return ''; // 未知slug
        }

        // 描画に必要な素の配列だけをキャッシュ（Eloquentモデルは入れない・HTMLもキャッシュしない）。
        $shops = Cache::remember("chain_shop_links_v1:{$slug}", 3600, function () use ($chain): array {
            return Shop::ofChain($chain)
                ->select('id', 'name', 'prefecture', 'city')
                ->get()
                ->map(fn (Shop $s): array => [
                    'id' => (int) $s->id,
                    'name' => (string) $s->name,
                    'prefecture' => trim((string) ($s->prefecture ?? '')),
                    'city' => (string) ($s->city ?? ''),
                ])
                ->all();
        });

        if (empty($shops)) {
            Log::warning('[chain-shops] 該当店舗0件のため空出力: '.$slug, ['slug' => $slug]);

            return ''; // 0件
        }

        // 都道府県ごとにグルーピング。prefecture 空は「その他」で最後にまとめる。
        $order = $this->prefectureOrder();
        $groups = [];
        foreach ($shops as $row) {
            $key = $row['prefecture'] !== '' ? $row['prefecture'] : '__other__';
            $groups[$key][] = $row;
        }

        // 全国標準順（北海道→沖縄）。未知都道府県は「その他」の直前、空は最後。
        uksort($groups, function (string $a, string $b) use ($order): int {
            $ra = $a === '__other__' ? PHP_INT_MAX : ($order[$a] ?? PHP_INT_MAX - 1);
            $rb = $b === '__other__' ? PHP_INT_MAX : ($order[$b] ?? PHP_INT_MAX - 1);

            return $ra <=> $rb;
        });

        $displayGroups = [];
        foreach ($groups as $key => $rows) {
            // 同一都道府県内は city → name の昇順。
            usort($rows, static fn (array $x, array $y): int => [$x['city'], $x['name']] <=> [$y['city'], $y['name']]);
            $displayGroups[] = [
                'label' => $key === '__other__' ? 'その他' : $key,
                'count' => count($rows),
                'shops' => array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => $r['name']], $rows),
            ];
        }

        return View::make('blog.partials.chain-shops', [
            'groups' => $displayGroups,
            'total' => count($shops),
        ])->render();
    }

    /**
     * 全国標準の都道府県並び（北海道→沖縄）を「フルネーム => 順位」で返す。
     * 既存の config('parking.regions')（フルネーム・地方別・標準順）を単一の出所として再利用。
     *
     * @return array<string, int>
     */
    private function prefectureOrder(): array
    {
        $order = [];
        foreach (config('parking.regions', []) as $prefs) {
            foreach (array_keys((array) $prefs) as $pref) {
                $order[(string) $pref] = count($order);
            }
        }

        return $order;
    }

    /**
     * [bikes ...] の中身（空白区切りの識別子）を車種に解決し、CTAブロックHTMLを返す。
     * 識別子は "mfrSlug/modelSlug"（主・/bikes/{mfr}/{slug} ルートと同一解決）または数値ID（フォールバック）。
     * 解決できない識別子はスキップ（誤リンクより無リンクを優先）。
     */
    private function renderBikesBlock(string $rawTokens): string
    {
        // parseParams と同じく、CommonMark がエスケープした実体参照を先に戻す（引用符なし用法では無変更）。
        $rawTokens = html_entity_decode($rawTokens, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tokens = preg_split('/[\s　]+/u', trim($rawTokens), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $bikes = [];
        $seen = [];
        foreach ($tokens as $token) {
            $model = $this->resolveModel($token);
            if ($model === null || isset($seen[$model->id])) {
                continue;
            }
            $seen[$model->id] = true;

            $stats = $this->priceStats->getModelStats($model->id);
            $count = (int) ($stats['count'] ?? 0);
            $minMan = isset($stats['min']) && $stats['min'] > 0 ? $stats['min'] : null;

            // アンカー表記は車種ページのタイトル構成（メーカー名＋車種名）に合わせる
            $label = trim(($model->manufacturer?->name ? $model->manufacturer->name . ' ' : '') . $model->name);

            $bikes[] = [
                'name' => $label,
                'url' => $model->seo_url,
                'count' => $count,
                'minMan' => $minMan,
            ];
        }

        if (empty($bikes)) {
            return '';
        }

        return View::make('blog.partials.bike-cta', ['bikes' => $bikes])->render();
    }

    /**
     * 識別子1つを車種に解決する。/bikes/{mfr}/{slug} ルートと同じロジックでページとリンク先を一致させる。
     */
    private function resolveModel(string $token): ?BikeModel
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        // 数値ID（フォールバック）
        if (ctype_digit($token)) {
            return BikeModel::with('manufacturer')->find((int) $token);
        }

        // mfrSlug/modelSlug（主）
        if (! str_contains($token, '/')) {
            return null;
        }
        [$mfrSlug, $modelSlug] = explode('/', $token, 2);
        $mfrSlug = trim($mfrSlug);
        $modelSlug = trim($modelSlug);
        if ($mfrSlug === '' || $modelSlug === '') {
            return null;
        }

        $manufacturer = Manufacturer::where('slug', $mfrSlug)->first();
        if ($manufacturer === null) {
            return null;
        }

        return BikeModel::with('manufacturer')
            ->where('manufacturer_id', $manufacturer->id)
            ->where('slug', $modelSlug)
            ->first();
    }

    /**
     * "key=value key2=value2" 形式のパラメータ文字列をパースする。
     *
     * @return array<string, string>
     */
    private function parseParams(string $raw): array
    {
        // CommonMark はテキストノードの " を &quot; にエスケープする（League\CommonMark\Util\Xml::escape）。
        // そのため processShortcodes に届く時点で slug=&quot;red-baron&quot; になり得る。
        // 捕捉したショートコード文字列「だけ」をデコードしてから解析する（HTML全体はデコードしない）。
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $params = [];
        // key="value" / key='value' / 引用符なし key=value のいずれも受け付ける。
        preg_match_all('/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // マッチした値グループ（2=ダブルクォート内/3=シングルクォート内/4=クォートなし）のうち非空を採用。
            $value = '';
            foreach ([$match[2] ?? '', $match[3] ?? '', $match[4] ?? ''] as $candidate) {
                if ($candidate !== '') {
                    $value = $candidate;
                    break;
                }
            }
            $params[$match[1]] = $value;
        }

        return $params;
    }
}
