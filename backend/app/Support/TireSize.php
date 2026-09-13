<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * タイヤサイズの表記ゆれを吸収する正規化と、「同じタイヤサイズの車種」抽出。
 *
 * bike_models.tire_size_front / tire_size_rear は同じサイズが複数表記で入っている
 * （"120/70ZR17" / "120/70ZR17M/C（58W）" / "120/70 ZR17" 等）。SQLでは突き合わせられないため、
 * PHP側で正規化してから一致判定する。全角マイナス "−" 等はデータ無しの意味なので null にする。
 */
final class TireSize
{
    /**
     * 全カード共通の縮尺。720mm を 92px として描く。カードごとに拡大縮小しない
     * （共通縮尺だからこそ 21インチと 10インチの大きさの差が実比率どおりに見える）。
     */
    public const PX_PER_MM = 92 / 720;

    /**
     * 生のタイヤ表記を比較用のコア文字列へ正規化する。突き合わせ不能・データ無しは null。
     */
    public static function normalize(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        // カンマ（半角/全角）区切りは「サイズ本体, ロードインデックス(53H), ホワイトウォール表記(BW)」等。
        // 先頭要素だけをサイズ本体として採用し、それ以降は捨てる（以降の既存処理は先頭要素に適用）。
        $s = preg_split('/[,，、]/u', $s, 2)[0];

        // 全角英数字・全角記号・全角空白を半角へ寄せる（（）／－ 等も半角化される）。
        $s = mb_convert_kana($s, 'as', 'UTF-8');

        // 括弧（半角・全角）とその中身を除去。例: （58W） (69W)
        $s = (string) preg_replace('/[（(][^）)]*[）)]/u', '', $s);

        // "M/C"（大小問わず）を除去。
        $s = (string) preg_replace('/m\/c/iu', '', $s);

        // 末尾のロードインデックス・速度記号（数字＋英字1文字）を除去。空白除去より前に行う
        // （空白を消してからだとリム径とLIの境界が失われ "120/80-14 58S" を誤って削るため）。
        $s = (string) preg_replace('/\s*\d{1,3}[A-Za-z]\s*$/u', '', $s);

        // 半角・全角の空白をすべて除去。
        $s = (string) preg_replace('/[\s\x{3000}]+/u', '', $s);

        // 英字は大文字へ。
        $s = strtoupper(trim($s));

        // 結果が2文字以下（"−"/"-"/"ー" 単体や短すぎる残骸）は無効。
        if (mb_strlen($s) <= 2) {
            return null;
        }

        return $s;
    }

    /**
     * 「同じタイヤサイズの車種」を返す。自車種の前サイズが正規化できなければ null（＝セクション非表示）。
     *
     * 返り値: ['mode' => 'both'|'front', 'items' => array<array{name:string, manufacturer:string, mfr_slug:string, model_slug:string}>]
     * - 前後一致が3件未満なら前輪のみ一致にフォールバック（mode='front'）。
     * - リンクは canonical URL（/bikes/{mfrSlug}/{modelSlug}）を組み立てるため mfr_slug/model_slug を持たせる。
     *   slug が欠ける車種はリンク不能なので除外する。
     * - 表示に必要な素の配列だけを Cache::remember へ保存（Eloquentモデルは入れない）。
     */
    public static function sameSizeModels(BikeModel $model): ?array
    {
        $selfFront = self::normalize($model->tire_size_front);
        if ($selfFront === null) {
            return null; // 自車種の前サイズ取得不可 → 呼び出し側でセクションごと非表示
        }
        $selfRear = self::normalize($model->tire_size_rear);
        $selfId = (int) $model->id;

        // normalize 変更（カンマ分割）で結果が変わるためキー版を v3 に上げる（旧 v1/v2 を読んで壊れるのを防ぐ）。
        return Cache::remember("tire_same_size_v3:{$selfId}", 86400, function () use ($selfId, $selfFront, $selfRear) {
            // 全件取得は1回のみ（キャッシュミス時だけ実行）。列を明示。
            $all = BikeModel::query()
                ->select('id', 'name', 'slug', 'manufacturer_id', 'tire_size_front', 'tire_size_rear')
                ->get();

            $both = [];
            $frontOnly = [];
            foreach ($all as $m) {
                if ((int) $m->id === $selfId) {
                    continue; // 自分自身を除外
                }
                $nf = self::normalize($m->tire_size_front);
                if ($nf === null || $nf !== $selfFront) {
                    continue; // 前輪一致は必須
                }
                $frontOnly[] = $m;
                if ($selfRear !== null) {
                    $nr = self::normalize($m->tire_size_rear);
                    if ($nr !== null && $nr === $selfRear) {
                        $both[] = $m; // 前後とも一致
                    }
                }
            }

            $mode = count($both) >= 3 ? 'both' : 'front';
            $matched = $mode === 'both' ? $both : $frontOnly;
            if (empty($matched)) {
                return ['mode' => $mode, 'items' => []];
            }

            // 候補ぶんだけ在庫数（is_sold_out=0）とメーカー名を1クエリずつ取得。
            $ids = array_map(static fn ($m): int => (int) $m->id, $matched);
            $stock = Listing::query()
                ->whereIn('bike_model_id', $ids)
                ->where('is_sold_out', 0)
                ->selectRaw('bike_model_id, COUNT(*) as cnt')
                ->groupBy('bike_model_id')
                ->pluck('cnt', 'bike_model_id');

            $mfrIds = array_values(array_unique(array_map(static fn ($m): int => (int) $m->manufacturer_id, $matched)));
            $mfrs = Manufacturer::whereIn('id', $mfrIds)->get(['id', 'name', 'slug'])->keyBy('id');

            // 並び順: 在庫あり優先 → 車種名昇順。
            usort($matched, static function ($a, $b) use ($stock): int {
                $sa = (int) ($stock[$a->id] ?? 0) > 0 ? 1 : 0;
                $sb = (int) ($stock[$b->id] ?? 0) > 0 ? 1 : 0;
                if ($sa !== $sb) {
                    return $sb <=> $sa; // 在庫ありを前へ
                }

                return strcmp((string) $a->name, (string) $b->name);
            });

            // slug が欠ける車種（メーカーslug or 車種slug が null/空）はリンク不能なので除外。順序を保って最大12件。
            $items = [];
            foreach ($matched as $m) {
                $modelSlug = trim((string) ($m->slug ?? ''));
                $mfr = $mfrs[$m->manufacturer_id] ?? null;
                $mfrSlug = trim((string) ($mfr->slug ?? ''));
                if ($modelSlug === '' || $mfrSlug === '') {
                    continue;
                }
                $items[] = [
                    'name' => (string) $m->name,
                    'manufacturer' => (string) ($mfr->name ?? ''),
                    'mfr_slug' => $mfrSlug,
                    'model_slug' => $modelSlug,
                ];
                if (count($items) >= 12) {
                    break;
                }
            }

            return ['mode' => $mode, 'items' => $items];
        });
    }

    /**
     * 正規化済みサイズ → URL用 sizeSlug。
     *
     * normalize() の出力は基本 A-Z0-9 と '/' '.' '-'。小文字化し '/' '.' をハイフンへ置換する。
     * それ以外の想定外記号（カンマ等）が万一残った場合は素通しするが、その slug は
     * ルート制約 [a-z0-9-]+ から外れる。ページ化可否は pageableIndex() が isValidSizeSlug() で判定し、
     * リンク不能な slug を持つサイズは索引から除外する（sizeSlug→サイズの往復変換はしない方針）。
     */
    public static function sizeSlug(string $normalized): string
    {
        return str_replace(['/', '.'], '-', strtolower($normalized));
    }

    /** sizeSlug がルート制約 [a-z0-9-]+ に収まる（リンク可能）か。 */
    public static function isValidSizeSlug(string $sizeSlug): bool
    {
        return $sizeSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $sizeSlug) === 1;
    }

    // 妥当性レンジ（推測で描かないための保険。範囲外は null）。
    private const ASPECT_MIN = 30;

    private const ASPECT_MAX = 120;

    private const WIDTH_MM_MIN = 40;

    private const WIDTH_MM_MAX = 300;

    private const RIM_MIN = 8;

    private const RIM_MAX = 23;

    /**
     * アルファニューメリック表記（ハーレー等の旧表記）の換算表。コード先頭4文字 => [断面幅mm, 扁平率%]。
     * コンチネンタル公式換算表。★末尾の 90/85 は扁平率ではないので計算せず必ずこの表で引く
     * （例: MV85 は 150/80）。表に無いコードは null（推測しない）。
     */
    private const ALPHA_CODES = [
        'MH90' => [80, 90],
        'MJ90' => [90, 90],
        'MM90' => [100, 90],
        'MN90' => [110, 90],
        'MP85' => [110, 90],
        'MR90' => [120, 90],
        'MT90' => [130, 90],
        'MU85' => [140, 90],
        'MU90' => [140, 90],
        'MV85' => [150, 80],
    ];

    /**
     * タイヤ表記から寸法を算出する。解釈できない・妥当でない表記は null（推測で描かないため）。
     *
     * メトリック（120/70ZR17 / 80/100-21 等）: 幅=第1数値mm、扁平=第2数値/100、リム=第3数値インチ。
     *   アスペクトとリムの間に英字（ZR/R/B 等）か「-」の区切りが必須。区切りが無い連結
     *   （100/9019 等の汚れデータ）は解釈しない。扁平率は 2〜3桁（オフ車の 100 表記を許可）。
     * インチ（2.75-21 等）: 幅=第1数値×25.4mm、扁平=1.0（バイアスの慣例で100%）。
     * アルファニューメリック（MT90B16 等）: 先頭4文字を ALPHA_CODES で引き、続く B（バイアスベルテッド
     *   構造記号）は読み飛ばし、残りの数字をリム径とする。表に無いコードは null。
     * サイドウォール=幅×扁平、外径=リム径×25.4 + サイドウォール×2。
     *
     * @return array{type:string,width_mm:float,aspect:float,rim_inch:int,sidewall_mm:float,outer_mm:float,equivalent:?string}|null
     */
    public static function dimensions(?string $raw): ?array
    {
        $s = self::normalize($raw);
        if ($s === null) {
            return null;
        }

        // 末尾の "MC"（motorcycle の正規サフィックス）を除去してから解釈する。
        // normalize() は "M/C"（スラッシュ付き）は既に除去するが、スラッシュ無しの "MC" は残る。
        // 大小・前後空白は normalize() で吸収済み（大文字化・空白除去）なので末尾 MC だけ剥がす。
        $s = (string) preg_replace('/MC$/', '', $s);

        // メトリック: 幅 / 扁平(2〜3桁) [英字|-] リム。区切り必須で 100/9019 のような連結を弾く。
        if (preg_match('/^(\d{2,3})\/(\d{2,3})(?:[A-Z]{1,2}|-)(\d{1,2})$/', $s, $m) === 1) {
            $width = (float) $m[1];
            $aspectPct = (float) $m[2];
            $rim = (int) $m[3];

            return self::buildDimensions('metric', $width, $aspectPct / 100, $rim);
        }

        // インチ（バイアス）: 幅(×25.4) - リム。扁平は100%固定。
        if (preg_match('/^(\d\.\d{2})-(\d{1,2})$/', $s, $m) === 1) {
            $width = ((float) $m[1]) * 25.4;
            $rim = (int) $m[2];

            return self::buildDimensions('inch', $width, 1.0, $rim);
        }

        // アルファニューメリック（ハーレー等の旧表記）。ハイフンを除去してから引く（MT90-B16 → MT90B16）。
        $alpha = str_replace('-', '', $s);
        if (preg_match('/^(M[A-Z]\d{2})B?(\d{1,2})$/', $alpha, $m) === 1) {
            $code = $m[1];
            if (! isset(self::ALPHA_CODES[$code])) {
                return null; // 表に無いコードは推測しない
            }

            [$width, $aspectPct] = self::ALPHA_CODES[$code];
            $rim = (int) $m[2];
            $dims = self::buildDimensions('alpha', (float) $width, $aspectPct / 100, $rim);
            if ($dims !== null) {
                // 元の表記のままカードに出すが、寸法テキストに換算値を添えるため保持する。
                $dims['equivalent'] = $width.'/'.$aspectPct.'-'.$rim;
            }

            return $dims;
        }

        return null;
    }

    /**
     * 妥当性チェック（扁平30〜120 / 断面幅40〜300mm / リム8〜23）を通ったものだけ寸法配列にする。
     * 範囲外は null（異常表記や汚れデータを図示しない）。
     *
     * @return array{type:string,width_mm:float,aspect:float,rim_inch:int,sidewall_mm:float,outer_mm:float,equivalent:?string}|null
     */
    private static function buildDimensions(string $type, float $widthMm, float $aspect, int $rim): ?array
    {
        $aspectPct = $aspect * 100;
        if ($aspectPct < self::ASPECT_MIN || $aspectPct > self::ASPECT_MAX) {
            return null;
        }
        if ($widthMm < self::WIDTH_MM_MIN || $widthMm > self::WIDTH_MM_MAX) {
            return null;
        }
        if ($rim < self::RIM_MIN || $rim > self::RIM_MAX) {
            return null;
        }

        $sidewall = $widthMm * $aspect;

        return [
            'type' => $type,
            'width_mm' => round($widthMm, 2),
            'aspect' => $aspect,
            'rim_inch' => $rim,
            'sidewall_mm' => round($sidewall, 2),
            'outer_mm' => round($rim * 25.4 + $sidewall * 2, 1),
            'equivalent' => null, // アルファニューメリックのみ換算値を後付けする
        ];
    }

    /**
     * 寸法から同心円のタイヤ断面 SVG を組み立てる。dimensions() が null なら null（図なし）。
     * トレッドは破線円1本で表現（線を並べると要素が膨れるため）。装飾なので aria-hidden。
     */
    public static function svg(?array $dimensions): ?string
    {
        if ($dimensions === null) {
            return null;
        }

        $px = self::PX_PER_MM;
        $outerR = round($dimensions['outer_mm'] / 2 * $px, 2);
        $rimR = round($dimensions['rim_inch'] * 25.4 / 2 * $px, 2);
        $hubR = round($rimR * 0.32, 2);
        $treadR = round($outerR - 2.7, 2);

        return '<svg viewBox="0 0 92 92" width="92" height="92" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">'
            .'<circle cx="46" cy="46" r="'.$outerR.'" fill="#1e293b"/>'
            .'<circle cx="46" cy="46" r="'.$treadR.'" fill="none" stroke="#94a3b8" stroke-width="3" stroke-dasharray="2 2.4"/>'
            .'<circle cx="46" cy="46" r="'.$rimR.'" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1"/>'
            .'<circle cx="46" cy="46" r="'.$hubR.'" fill="#cbd5e1"/>'
            .'</svg>';
    }

    /**
     * カードに出す寸法テキスト。メトリックは扁平率あり、インチは扁平率を出さない。null なら null。
     */
    public static function dimensionText(?array $dimensions): ?string
    {
        if ($dimensions === null) {
            return null;
        }

        $outer = (int) round($dimensions['outer_mm']);

        // インチ（バイアス）は扁平率を出さない。
        if ($dimensions['type'] === 'inch') {
            $width = (int) round($dimensions['width_mm']);

            return "幅約{$width}mm・外径約{$outer}mm";
        }

        // メトリック / アルファニューメリックは扁平率あり。後者は換算値を添える。
        $width = (int) round($dimensions['width_mm']);
        $aspect = (int) round($dimensions['aspect'] * 100);
        $text = "幅{$width}mm・扁平{$aspect}%・外径約{$outer}mm";

        if ($dimensions['type'] === 'alpha' && ! empty($dimensions['equivalent'])) {
            $text .= "（≒{$dimensions['equivalent']} 相当）";
        }

        return $text;
    }

    /** リム径 → グループキー。dimensions() が null（判定不能）や規定外リムは 'other'。 */
    private static function rimBucket(?array $dimensions): string
    {
        if ($dimensions === null) {
            return 'other';
        }

        $rim = (int) $dimensions['rim_inch'];

        return match (true) {
            $rim === 21 => '21',
            $rim === 19 => '19',
            $rim === 18 => '18',
            $rim === 17 => '17',
            $rim === 16 => '16',
            $rim === 15 => '15',
            $rim >= 12 && $rim <= 14 => '12-14',
            $rim <= 10 => '10',
            default => 'other', // 11/20 等の稀なリムは索引の見出しに無いのでその他へ
        };
    }

    /** 索引ページのリムグループ定義（表示順・見出し・説明）。 */
    private const RIM_GROUPS = [
        ['key' => '21', 'label' => '21インチ', 'desc' => 'オフロード・アドベンチャーの前輪'],
        ['key' => '19', 'label' => '19インチ', 'desc' => 'クラシック・クルーザーの前輪'],
        ['key' => '18', 'label' => '18インチ', 'desc' => '旧車・ネイキッドの前輪'],
        ['key' => '17', 'label' => '17インチ', 'desc' => 'スポーツ・ネイキッドの前輪（最も多い）'],
        ['key' => '16', 'label' => '16インチ', 'desc' => 'アメリカン・大型クルーザーの前輪'],
        ['key' => '15', 'label' => '15インチ', 'desc' => 'ビッグスクーターの前輪'],
        ['key' => '12-14', 'label' => '12〜14インチ', 'desc' => 'スクーター・ミニバイク'],
        ['key' => '10', 'label' => '10インチ以下', 'desc' => '原付スクーター'],
        ['key' => 'other', 'label' => 'その他', 'desc' => '寸法を図示できない表記のサイズ'],
    ];

    /**
     * 索引ページ用データ。ページ化サイズ（前輪一致5件以上）をリム径でグループ化し、
     * 各サイズに寸法・SVG・寸法テキスト・代表車種名（display_name 上位3）を添える。
     *
     * N+1 回避: 全車種を1クエリ、在庫を1クエリで取得し PHP 側で振り分ける（カード毎に引かない）。
     * SVG・テキスト・車種名は素の文字列だけをキャッシュ（Eloquent は入れない）。
     *
     * @return array<int, array{label:string, desc:string, count:int, sizes:array<int, array<string,mixed>>}>
     */
    public static function indexData(): array
    {
        return Cache::remember('tire_size_index_page_v1', 86400, function (): array {
            $all = BikeModel::query()
                ->select('id', 'name', 'display_name', 'manufacturer_id', 'tire_size_front')
                ->get();

            $groups = []; // 正規化サイズ => メンバー車種
            foreach ($all as $m) {
                $nf = self::normalize($m->tire_size_front);
                if ($nf !== null) {
                    $groups[$nf][] = $m;
                }
            }
            $pageable = array_filter($groups, static fn (array $members): bool => count($members) >= 5);

            // 在庫（is_sold_out=0）をページ化メンバー全部まとめて1クエリ。
            $allIds = [];
            foreach ($pageable as $members) {
                foreach ($members as $m) {
                    $allIds[] = (int) $m->id;
                }
            }
            $stock = empty($allIds)
                ? collect()
                : Listing::query()->whereIn('bike_model_id', $allIds)->where('is_sold_out', 0)
                    ->selectRaw('bike_model_id, COUNT(*) as cnt')->groupBy('bike_model_id')->pluck('cnt', 'bike_model_id');

            $cards = [];
            foreach ($pageable as $size => $members) {
                // ルート制約に収まらない slug（想定外記号残り）はリンク不能なので索引から除外。
                $slug = self::sizeSlug((string) $size);
                if (! self::isValidSizeSlug($slug)) {
                    continue;
                }

                // 在庫多い順 → 車種名昇順（代表車種の選出順）。
                usort($members, static function ($a, $b) use ($stock): int {
                    $sa = (int) ($stock[$a->id] ?? 0);
                    $sb = (int) ($stock[$b->id] ?? 0);
                    if ($sa !== $sb) {
                        return $sb <=> $sa;
                    }

                    return strcmp((string) $a->name, (string) $b->name);
                });

                // 代表車種名は display_name のみ（name の小文字表示は出さない）。最大3件。
                $names = [];
                foreach ($members as $m) {
                    $dn = trim((string) ($m->display_name ?? ''));
                    if ($dn === '') {
                        continue;
                    }
                    $names[] = $dn;
                    if (count($names) >= 3) {
                        break;
                    }
                }

                $count = count($members);
                $dims = self::dimensions((string) $size);

                $cards[] = [
                    'size' => (string) $size,
                    'size_slug' => $slug,
                    'count' => $count,
                    'svg' => self::svg($dims),
                    'dim_text' => self::dimensionText($dims),
                    'names' => $names,
                    'more' => $count > count($names), // 代表名より車種が多ければ「ほか」
                    'rim_bucket' => self::rimBucket($dims),
                ];
            }

            // 規定のリム順にグループ化。各グループ内は車種数の多い順。空グループは出さない。
            $out = [];
            foreach (self::RIM_GROUPS as $def) {
                $inGroup = array_values(array_filter($cards, static fn (array $c): bool => $c['rim_bucket'] === $def['key']));
                if (empty($inGroup)) {
                    continue;
                }
                usort($inGroup, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

                $out[] = [
                    'label' => $def['label'],
                    'desc' => $def['desc'],
                    'count' => count($inGroup),
                    'sizes' => $inGroup,
                ];
            }

            return $out;
        });
    }

    /**
     * 車種1件の代表画像URLを列だけから解決（クエリ非発行）。無ければ null。
     *
     * 既存の BikeModel::imageUrl アクセサは Listing を都度引くためループでN+1になる。
     * ここではそのクエリ非依存部分を再利用: 既存ヘルパ model_image_url()（models/配下の唯一の切替口）で
     * local_image_path を解決し、無ければアクセサに隠れる生の image_url 列（models 一覧ビューと同じ http/asset 判定）。
     * 呼び出し側で local_image_path / image_url を select 済みであること。
     */
    private static function resolveModelImage(BikeModel $m): ?string
    {
        $local = $m->local_image_path; // 'array' キャスト
        if (is_array($local) && ! empty($local)) {
            return model_image_url(ltrim((string) $local[0], '/'));
        }

        $raw = trim((string) ($m->getRawOriginal('image_url') ?? '')); // アクセサを迂回して生の列値
        if ($raw !== '') {
            return Str::startsWith($raw, ['http://', 'https://']) ? $raw : asset($raw);
        }

        return null;
    }

    /**
     * ページ化対象（前輪サイズごとの該当車種が5件以上）のサイズ索引。多い順。
     * 各サイズに代表画像（在庫多い順→名前昇順・画像有りのみ・最大3枚）を添える。
     * 返り値: array<array{size:string, size_slug:string, count:int, images:array<array{url:string,name:string}>}>
     */
    public static function pageableIndex(): array
    {
        // normalize 変更（カンマ分割）と画像列を含むので v3（旧 v1/v2 の配列を読んで壊れるのを防ぐ）。
        return Cache::remember('tire_size_index_v3', 86400, function (): array {
            // 画像解決に必要な列（image_url / local_image_path）を最初の1クエリで取得。
            $all = BikeModel::query()
                ->select('id', 'name', 'manufacturer_id', 'tire_size_front', 'image_url', 'local_image_path')
                ->get();

            $groups = []; // 正規化サイズ => メンバー車種
            foreach ($all as $m) {
                $nf = self::normalize($m->tire_size_front);
                if ($nf !== null) {
                    $groups[$nf][] = $m;
                }
            }
            $pageable = array_filter($groups, static fn (array $members): bool => count($members) >= 5);

            // ページ化対象メンバー全部の在庫を1クエリで（車種ごとのループでは引かない）。
            $allIds = [];
            foreach ($pageable as $members) {
                foreach ($members as $m) {
                    $allIds[] = (int) $m->id;
                }
            }
            $stock = empty($allIds)
                ? collect()
                : Listing::query()->whereIn('bike_model_id', $allIds)->where('is_sold_out', 0)
                    ->selectRaw('bike_model_id, COUNT(*) as cnt')->groupBy('bike_model_id')->pluck('cnt', 'bike_model_id');

            $index = [];
            foreach ($pageable as $size => $members) {
                // ルート制約 [a-z0-9-]+ に収まらない slug（カンマ等の想定外記号残り）はリンク不能なので索引に入れない。
                $slug = self::sizeSlug((string) $size);
                if (! self::isValidSizeSlug($slug)) {
                    continue;
                }

                // 在庫多い順 → 車種名昇順。
                usort($members, static function ($a, $b) use ($stock): int {
                    $sa = (int) ($stock[$a->id] ?? 0);
                    $sb = (int) ($stock[$b->id] ?? 0);
                    if ($sa !== $sb) {
                        return $sb <=> $sa;
                    }

                    return strcmp((string) $a->name, (string) $b->name);
                });

                // 画像有りの車種から最大3枚（無い車種は飛ばす）。
                $images = [];
                foreach ($members as $m) {
                    $url = self::resolveModelImage($m);
                    if ($url !== null) {
                        $images[] = ['url' => $url, 'name' => (string) $m->name];
                    }
                    if (count($images) >= 3) {
                        break;
                    }
                }

                $index[] = [
                    'size' => (string) $size,
                    'size_slug' => $slug,
                    'count' => count($members),
                    'images' => $images, // 0〜3枚
                ];
            }
            usort($index, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

            return $index;
        });
    }

    /** 正規化サイズがページ化条件（5件以上）を満たすか。 */
    public static function isPageable(string $normalizedSize): bool
    {
        foreach (self::pageableIndex() as $row) {
            if ($row['size'] === $normalizedSize) {
                return true;
            }
        }

        return false;
    }

    /** sizeSlug に対応する正規化サイズ（索引から探す・往復変換しない）。無ければ null。 */
    public static function sizeFromSlug(string $sizeSlug): ?string
    {
        foreach (self::pageableIndex() as $row) {
            if ($row['size_slug'] === $sizeSlug) {
                return $row['size'];
            }
        }

        return null;
    }

    /**
     * サイズ別ページの集計（素の配列・DB cache）。ページ化条件を満たさない/未知slugなら null（→404）。
     * 価格カラムは price（車両本体価格）を使用。在庫は listings.is_sold_out=0。
     */
    public static function pageData(string $sizeSlug): ?array
    {
        $size = self::sizeFromSlug($sizeSlug);
        if ($size === null) {
            return null;
        }

        // normalize 変更（カンマ分割）と画像列を含むので v3（旧 v1/v2 を読んで壊れるのを防ぐ）。
        return Cache::remember("tire_size_page_v3:{$sizeSlug}", 86400, function () use ($size, $sizeSlug): array {
            $all = BikeModel::query()
                ->select('id', 'name', 'slug', 'manufacturer_id', 'tire_size_front', 'tire_size_rear', 'image_url', 'local_image_path')
                ->get();

            $matched = [];
            $rearCounts = []; // 正規化後輪サイズ => 車種数
            foreach ($all as $m) {
                if (self::normalize($m->tire_size_front) !== $size) {
                    continue;
                }
                $matched[] = $m;
                $nr = self::normalize($m->tire_size_rear);
                if ($nr !== null) {
                    $rearCounts[$nr] = ($rearCounts[$nr] ?? 0) + 1;
                }
            }

            $totalModels = count($matched);
            $ids = array_map(static fn ($m): int => (int) $m->id, $matched);

            // 在庫（is_sold_out=0）: 車種別台数を1クエリ。
            $stock = Listing::query()
                ->whereIn('bike_model_id', $ids)
                ->where('is_sold_out', 0)
                ->selectRaw('bike_model_id, COUNT(*) as cnt')
                ->groupBy('bike_model_id')
                ->pluck('cnt', 'bike_model_id');

            // 価格集計（total_price・is_sold_out=0・total_price>0）を1クエリ。
            // 既存の検索フィルタ/ソート/相場統計に合わせて total_price（支払総額）を使う。
            $priceAgg = Listing::query()
                ->whereIn('bike_model_id', $ids)
                ->where('is_sold_out', 0)
                ->where('total_price', '>', 0)
                ->selectRaw('COUNT(*) as cnt, MIN(total_price) as min_p, MAX(total_price) as max_p, AVG(total_price) as avg_p')
                ->first();

            $stockTotal = 0;
            $modelsWithStock = 0;
            foreach ($stock as $cnt) {
                $stockTotal += (int) $cnt;
                if ((int) $cnt > 0) {
                    $modelsWithStock++;
                }
            }

            $mfrIds = array_values(array_unique(array_map(static fn ($m): int => (int) $m->manufacturer_id, $matched)));
            $mfrs = Manufacturer::whereIn('id', $mfrIds)->get(['id', 'name', 'slug'])->keyBy('id');

            // 並び順: 在庫あり優先 → 車種名昇順。
            usort($matched, static function ($a, $b) use ($stock): int {
                $sa = (int) ($stock[$a->id] ?? 0) > 0 ? 1 : 0;
                $sb = (int) ($stock[$b->id] ?? 0) > 0 ? 1 : 0;
                if ($sa !== $sb) {
                    return $sb <=> $sa;
                }

                return strcmp((string) $a->name, (string) $b->name);
            });

            // slug 欠落は除外して最大60件（順序維持）。
            $items = [];
            foreach ($matched as $m) {
                $modelSlug = trim((string) ($m->slug ?? ''));
                $mfr = $mfrs[$m->manufacturer_id] ?? null;
                $mfrSlug = trim((string) ($mfr->slug ?? ''));
                if ($modelSlug === '' || $mfrSlug === '') {
                    continue;
                }
                $items[] = [
                    'name' => (string) $m->name,
                    'manufacturer' => (string) ($mfr->name ?? ''),
                    'mfr_slug' => $mfrSlug,
                    'model_slug' => $modelSlug,
                    'stock' => (int) ($stock[$m->id] ?? 0),
                    'image' => self::resolveModelImage($m), // 列のみ・クエリ非発行。null は placeholder 表示。
                ];
                if (count($items) >= 60) {
                    break;
                }
            }

            // 後輪組み合わせ 上位10（リンクにはしない）。
            arsort($rearCounts);
            $rear = [];
            foreach ($rearCounts as $rs => $c) {
                $rear[] = ['size' => (string) $rs, 'count' => (int) $c];
                if (count($rear) >= 10) {
                    break;
                }
            }

            $hasStock = $stockTotal > 0 && $priceAgg && (int) $priceAgg->cnt > 0;

            return [
                'size' => $size,
                'size_slug' => $sizeSlug,
                'total_models' => (int) $totalModels,
                'models_with_stock' => (int) $modelsWithStock,
                'stock_total' => (int) $stockTotal,
                'price' => $hasStock ? [
                    'min' => (int) $priceAgg->min_p,
                    'max' => (int) $priceAgg->max_p,
                    'avg' => (int) round((float) $priceAgg->avg_p),
                ] : null,
                'items' => $items,
                'rear' => $rear,
                'sample_names' => array_map(static fn (array $it): string => $it['name'], array_slice($items, 0, 3)),
            ];
        });
    }
}
