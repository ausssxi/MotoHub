<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeModel;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 新基準原付ハブ（データハブ・ランキング獲得）。恒久slug /shinkijun-gentsuki。
 * 中古アグリゲータの独自データ＝「新基準原付(Lite)の新古車実在庫・相場 vs 旧110cc中古 vs 旧50cc中古」。
 * 数値は全て実在庫からの集計。無い項目は断定しない（在庫0の車種は"在庫◯台"と偽らない）。
 */
final class ShinkijunGentsukiController extends Controller
{
    public function show(): View
    {
        $data = Cache::remember('shinkijun_hub_v1', 3600, function () {
            return [
                'targets' => $this->resolveGroup(config('shinkijun.target_models')),
                'bases' => $this->resolveGroup(config('shinkijun.base_models')),
                'old50' => $this->resolveGroup(config('shinkijun.old50_models')),
            ];
        });

        // 4層比較のグループ集計（実在庫のみ）
        $data['summary'] = [
            'target' => $this->groupSummary($data['targets']),
            'base' => $this->groupSummary($data['bases']),
            'old50' => $this->groupSummary($data['old50']),
        ];

        return view('shinkijun-gentsuki', $data);
    }

    /**
     * @param  array<int, string>  $names
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolveGroup(array $names): Collection
    {
        return collect($names)
            ->map(fn (string $name) => $this->resolveModel($name))
            ->filter() // 未解決 / 在庫0 は落とす（偽データ防止）
            ->values();
    }

    /**
     * 正確名で車種を解決し、実在庫の集計を返す（在庫0なら null）。
     *
     * @return array<string, mixed>|null
     */
    private function resolveModel(string $name): ?array
    {
        $model = BikeModel::with('manufacturer')
            ->where('name', $name)
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->first();

        if ($model === null || (int) $model->listings_count === 0) {
            return null;
        }

        $base = Listing::where('bike_model_id', $model->id)->where('is_sold_out', false);
        $priced = (clone $base)->where('total_price', '>', 0);
        $agg = (clone $priced)->selectRaw('AVG(total_price) avg_p, MIN(total_price) min_p')->first();
        // 走行距離（新古車ほど低い＝Lite の訴求点）。将来年式(2027等)の外れ値は年式平均から除外。
        $avgMileage = (clone $base)->where('mileage', '>', 0)->avg('mileage');

        return [
            'id' => $model->id,
            'name' => $model->name,
            'maker' => $model->manufacturer?->name ?? '',
            'seo_url' => $model->seo_url,
            'count' => (int) $model->listings_count,
            'avg_man' => $agg && $agg->avg_p ? round(((float) $agg->avg_p) / 10000, 1) : null,
            'min_man' => $agg && $agg->min_p ? round(((float) $agg->min_p) / 10000, 1) : null,
            'avg_mileage' => $avgMileage ? (int) round((float) $avgMileage) : null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function groupSummary(Collection $group): array
    {
        $count = (int) $group->sum('count');
        $avgs = $group->pluck('avg_man')->filter();

        return [
            'total_count' => $count,
            // 相場レンジ＝各モデル平均の最小〜最大（実データ。無ければ null）
            'low_man' => $avgs->isNotEmpty() ? $avgs->min() : null,
            'high_man' => $avgs->isNotEmpty() ? $avgs->max() : null,
        ];
    }
}
