<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 中古市場「全体」（車種を絞らない）のマクロ統計：地域 / 年式 / 走行距離帯。
 *
 * スコープは車種別 ModelStatsService と統一：Listing::cappedSold(3ヶ月前, now)->excludeBulkSold()。
 * - 地域：RankingController::buildPrefectureRanking() のロジックをここへ集約（CSVエクスポートと共有・出力不変）。
 * - 年式・走行距離：市場全体の集計が無かったため新規。表記/バケットは ModelStatsService と一致させる。
 *
 * 市場全体の groupBy は重いため結果を1時間キャッシュ（毎回フル集計しない）。
 */
final class MarketStatsService
{
    private const CACHE_KEY = 'market_stats_v1';

    private const CACHE_TTL = 3600; // 1時間

    /** 集計対象期間（直近◯ヶ月の成約）。車種別APIと統一。 */
    public const MONTHS = 3;

    /**
     * 市場全体の統計（地域 / 年式 / 走行距離帯）を返す。1時間キャッシュ。
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->compute());
    }

    /**
     * 都道府県別 成約台数ランキング。RankingController のCSVエクスポートと共有。
     * 渡された Listing クエリ（スコープ適用済み）に対して集計する＝呼び出し側がスコープを決める。
     *
     * @return Collection<int, \App\Models\Listing>
     */
    public function prefectureRanking(Builder $query): Collection
    {
        return (clone $query)->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->select('shops.prefecture', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('shops.prefecture')
            ->orderByDesc('sold_count')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(): array
    {
        $now = Carbon::now();
        $from = $now->copy()->subMonths(self::MONTHS);

        // 全集計で同一スコープ（直近3ヶ月成約・全車種）。cappedSold はサブクエリのため都度生成する。
        $scope = fn () => Listing::cappedSold($from, $now)->excludeBulkSold();

        // 地域（CSVと同じ buildPrefectureRanking ロジックを共有）
        $regions = $this->prefectureRanking($scope());

        // 年式（新しい順）。車種別 yearRanking と同じ粒度（model_year groupBy・desc）に加え、
        // 市場全体では不正値（例: model_year=5010）が新しい順の先頭に出るため健全範囲に制限する。
        // ※車種別ページ側は1車種スコープ＋limit10で表面化しないため、ここでの制限のみ。挙動は変えない。
        $years = $scope()
            ->whereNotNull('model_year')
            ->whereBetween('model_year', [1900, $now->year + 1])
            ->select('model_year', DB::raw('COUNT(*) as cnt'))
            ->groupBy('model_year')
            ->orderByDesc('model_year')
            ->get();

        // 走行距離帯。車種別 mileageRanges と完全に同じ CASE バケットを使用（表記も一致）。
        $mileageRanges = $scope()
            ->whereNotNull('mileage')
            ->select(DB::raw("
                CASE
                    WHEN mileage < 5000 THEN '〜5,000km'
                    WHEN mileage < 10000 THEN '5,000〜10,000km'
                    WHEN mileage < 20000 THEN '10,000〜20,000km'
                    WHEN mileage < 30000 THEN '20,000〜30,000km'
                    ELSE '30,000km〜'
                END as mileage_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('mileage_range')
            ->orderByDesc('cnt')
            ->get();

        return [
            'from' => $from->toDateString(),
            'to' => $now->toDateString(),
            'regions' => $regions,
            'years' => $years,
            'mileageRanges' => $mileageRanges,
        ];
    }
}
