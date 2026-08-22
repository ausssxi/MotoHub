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
     * 生のタイヤ表記を比較用のコア文字列へ正規化する。突き合わせ不能・データ無しは null。
     */
    public static function normalize(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

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

        // 配列の形が変わったのでキー版を v2 に上げる（旧 v1 の配列を新コードが読んで壊れるのを防ぐ）。
        return Cache::remember("tire_same_size_v2:{$selfId}", 86400, function () use ($selfId, $selfFront, $selfRear) {
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
     * normalize() の出力は A-Z0-9 と '/' '.' '-' のみ（空白・括弧・M/C・全角は除去済み・英字は大文字）。
     * 小文字化し、'/' と '.' をハイフンへ置換する。'/' '.' 以外の記号が混じっても素通しするが、
     * ルートの where 制約 [a-z0-9-]+ から外れる値は 404 になる（sizeSlug→サイズの往復変換はしない方針）。
     */
    public static function sizeSlug(string $normalized): string
    {
        return str_replace(['/', '.'], '-', strtolower($normalized));
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
        // 画像列を含むので v2（旧 v1 の配列を読んで壊れるのを防ぐ）。
        return Cache::remember('tire_size_index_v2', 86400, function (): array {
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
                    'size_slug' => self::sizeSlug((string) $size),
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

        // 画像列を含むので v2（旧 v1 の配列を読んで壊れるのを防ぐ）。
        return Cache::remember("tire_size_page_v2:{$sizeSlug}", 86400, function () use ($size, $sizeSlug): array {
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
