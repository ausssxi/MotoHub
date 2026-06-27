<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * 排気量クラス別「掲載台数ランキング」集計（画像生成コマンドとデータAPIの共有ロジック）。
 *
 * 在庫(is_sold_out=false)を車種別に COUNT・降順、平均価格付き。
 * 売り切れ系scope(cappedSold/excludeBulkSold)は使わない。
 * 排気量帯は site 既存定義（comparison.php / sitemap $ccRanges）に一致。
 */
final class ClassRankingService
{
    /**
     * クラス → [下限cc, 上限cc(nullは上限なし)]。
     *
     * ★厳密な公称クラス帯（ライセンス区分の 126-250 等ではない）。
     *   250クラスに 150/155/160cc（GIXXER 150・PCX160・NMAX155・XSR155 等）が混ざらないよう、
     *   各クラスを「公称cc-24 〜 公称cc」の25cc幅に厳密化する（例: 250 = 226-250）。
     *   この結果、帯の谷間（126-225, 251-347 等）の車種はどのクラスにも入らない＝意図的に除外。
     *   400 のみ下限を 348 とし GB350(348cc) を含める（376→348）。
     *   401cc以上は middle(401-750) と large(751-) に二分（旧 large=401- を分割）。
     *   サイト(RankingController)・データAPI(RankingApiController)・画像コマンド
     *   (motohub:gen-class-ranking) の全てがこの定義を共有＝数字が一致する。
     *   個別調整は --min/--max（画像）でも可能。
     */
    public const RANGES = [
        '50' => [1, 50],
        '125' => [101, 125],
        '250' => [226, 250],
        '400' => [348, 400],
        'middle' => [401, 750],
        'large' => [751, null],
    ];

    /** @return array<int, string> 受理するクラス値 */
    public static function classes(): array
    {
        return array_keys(self::RANGES);
    }

    /**
     * 入力クラスを正規化（'大型' 等のエイリアスを 'large' に寄せる）。未知なら null。
     */
    public static function normalizeClass(string $class): ?string
    {
        $c = strtolower(trim($class));
        // 401cc以上は middle/large に二分したため、旧 '401'/'over400'→large エイリアスは廃止
        // （どちらの帯を指すか曖昧になるため）。'大型'(=751cc〜) のみ別名で受理。
        $aliases = ['大型' => 'large'];
        $c = $aliases[$c] ?? $c;

        return isset(self::RANGES[$c]) ? $c : null;
    }

    /**
     * クラス別 掲載台数ランキングを返す。
     *
     * @param  string  $class  '50'|'125'|'250'|'400'|'middle'|'large'（正規化済み前提・未知は空配列）
     * @param  int|null  $minOverride  排気量下限の上書き（厳密な226-250等にしたい時）
     * @param  int|null  $maxOverride  排気量上限の上書き
     * @param  bool  $withLinks  true でサイト内リンク用に bike_model_id / seo_url / image_url も返す（API既定出力は false で不変）
     * @return array<int, array{rank:int, model:string, maker:string, count:int, avg_price_man:?int}>
     */
    public function classRanking(string $class, int $limit = 20, ?int $minOverride = null, ?int $maxOverride = null, bool $withLinks = false): array
    {
        if (! isset(self::RANGES[$class])) {
            return [];
        }

        [$min, $max] = self::RANGES[$class];
        if ($minOverride !== null) {
            $min = $minOverride;
        }
        if ($maxOverride !== null) {
            $max = $maxOverride;
        }

        $limit = max(1, min(50, $limit));

        $query = Listing::where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->whereNotNull('bike_models.displacement')
            ->where('bike_models.displacement', '>', 0)
            ->where('bike_models.displacement', '>=', $min);
        if ($max !== null) {
            $query->where('bike_models.displacement', '<=', $max);
        }

        $rankings = $query
            ->select('listings.bike_model_id', DB::raw('COUNT(*) as stock_count'), DB::raw('AVG(listings.total_price) as avg_price'))
            ->groupBy('listings.bike_model_id')
            ->orderByDesc('stock_count')
            ->limit($limit)
            ->get();

        // withLinks時のみ image_url（代表Listing画像にフォールバック）を解決するため
        // representativeListing を eager load。N+1を避け、車種ぶんまとめて1クエリで引く。
        $with = ['manufacturer'];
        if ($withLinks) {
            $with[] = 'representativeListing';
        }
        $models = BikeModel::with($with)
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        return $rankings->values()->map(function ($row, $i) use ($models, $withLinks) {
            $model = $models->get($row->bike_model_id);

            $out = [
                'rank' => $i + 1,
                'model' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'count' => (int) $row->stock_count,
                'avg_price_man' => $row->avg_price ? (int) round($row->avg_price / 10000) : null,
            ];

            // サイト内リンク・サムネ用（API既定出力には含めない＝公開APIに内部ID/画像を漏らさない）
            if ($withLinks) {
                $out['bike_model_id'] = (int) $row->bike_model_id;
                $out['seo_url'] = $model?->seo_url;
                $out['image_url'] = $model?->image_url; // TOP20と同じ image_url アクセサを流用
            }

            return $out;
        })->all();
    }
}
