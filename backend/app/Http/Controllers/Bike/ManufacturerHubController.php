<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * メーカー単体ハブページ（/bikes/{makerSlug}）。従来は404で、着地は noindex の検索URLしか無かった。
 * クリーンで索引可能な「厚い」メーカーハブに置き換え、車種ページ（スポーク）への内部リンクを集約する。
 * area-index と同型の BreadcrumbList + ItemList・鮮度表示・中古主軸フレーミングを踏襲。
 */
final class ManufacturerHubController extends Controller
{
    public function show(string $makerSlug): View
    {
        $manufacturer = Manufacturer::where('slug', $makerSlug)->first();
        abort_if($manufacturer === null, 404); // slug未解決は404（防御的・catch-all誤爆を弾く）

        $data = Cache::remember("manufacturer_hub_v1_{$manufacturer->id}", 3600, function () use ($manufacturer) {
            $base = Listing::where('manufacturer_id', $manufacturer->id)->where('is_sold_out', false);
            $totalCount = (clone $base)->count();

            $agg = (clone $base)->where('total_price', '>', 0)
                ->selectRaw('AVG(total_price) as avg_p, MIN(total_price) as min_p, MAX(total_price) as max_p')
                ->first();

            // 人気モデル（active在庫数降順）＝ハブ→スポークの内部リンク＆ItemList。manufacturer eager で seo_url のN+1回避。
            $models = BikeModel::where('manufacturer_id', $manufacturer->id)
                ->whereNotNull('slug')
                ->with('manufacturer')
                ->withCount(['listings' => fn ($q) => $q->active()])
                ->orderByDesc('listings_count')
                ->limit(30)
                ->get()
                ->filter(fn ($m) => $m->listings_count > 0)
                ->take(24)
                ->values();

            return [
                'totalCount' => $totalCount,
                'avgMan' => $agg && $agg->avg_p ? round(((float) $agg->avg_p) / 10000, 1) : null,
                'minMan' => $agg && $agg->min_p ? round(((float) $agg->min_p) / 10000, 1) : null,
                'maxMan' => $agg && $agg->max_p ? round(((float) $agg->max_p) / 10000, 1) : null,
                'models' => $models,
            ];
        });

        return view('bikes.manufacturer-hub', array_merge($data, ['manufacturer' => $manufacturer]));
    }
}
