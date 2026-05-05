<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OverseasBikeService
{
    private const DOMESTIC_NAMES = ['ホンダ', 'ヤマハ', 'カワサキ', 'スズキ'];
    private const CACHE_TTL = 21600; // 6 hours

    public function getIndexData(): array
    {
        return Cache::remember('overseas_index', self::CACHE_TTL, function () {
            $manufacturers = $this->getOverseasManufacturers();
            $mfrIds = $manufacturers->pluck('id');

            $stats = $this->getOverallStats($mfrIds);
            $makerCards = $this->getMakerCards($manufacturers);
            $priceDistribution = $this->getPriceDistribution($mfrIds);
            $newArrivals = $this->getNewArrivals($mfrIds, 10);

            return [
                'manufacturers' => $manufacturers,
                'stats' => $stats,
                'makerCards' => $makerCards,
                'priceDistribution' => $priceDistribution,
                'newArrivals' => $newArrivals,
            ];
        });
    }

    public function getMakerData(string $makerSlug): ?array
    {
        return Cache::remember("overseas_maker:{$makerSlug}", self::CACHE_TTL, function () use ($makerSlug) {
            $manufacturer = Manufacturer::where('slug', $makerSlug)->first();
            if (! $manufacturer) {
                return null;
            }

            // Verify it's overseas
            if (in_array($manufacturer->name, self::DOMESTIC_NAMES, true)) {
                return null;
            }

            $mfrIds = collect([$manufacturer->id]);
            $stats = $this->getMakerStats($manufacturer);
            $topModels = $this->getTopModels($manufacturer, 10);
            $priceDistribution = $this->getPriceDistribution($mfrIds);
            $yearDistribution = $this->getYearDistribution($manufacturer);
            $newArrivals = $this->getNewArrivals($mfrIds, 10);
            $otherMakers = $this->getOtherMakers($manufacturer->id);

            $heroListing = Listing::where('is_sold_out', false)
                ->whereHas('bikeModel', fn ($q) => $q->where('manufacturer_id', $manufacturer->id))
                ->whereNotNull('image_urls')
                ->orderByDesc('created_at')
                ->first();
            $heroImage = $heroListing?->images[0] ?? null;

            return [
                'manufacturer' => $manufacturer,
                'stats' => $stats,
                'topModels' => $topModels,
                'priceDistribution' => $priceDistribution,
                'yearDistribution' => $yearDistribution,
                'newArrivals' => $newArrivals,
                'otherMakers' => $otherMakers,
                'heroImage' => $heroImage,
            ];
        });
    }

    public function getOverseasManufacturers(): Collection
    {
        return Manufacturer::whereNotIn('name', self::DOMESTIC_NAMES)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('bikeModels.listings', fn ($q) => $q->where('is_sold_out', false))
            ->withCount(['bikeModels as active_listings_count' => function ($q) {
                $q->whereHas('listings', fn ($lq) => $lq->where('is_sold_out', false));
            }])
            ->orderByDesc('active_listings_count')
            ->get();
    }

    private function getOverallStats(Collection $mfrIds): array
    {
        $query = Listing::where('is_sold_out', false)
            ->whereHas('bikeModel', fn ($q) => $q->whereIn('manufacturer_id', $mfrIds));

        $count = $query->count();
        $prices = (clone $query)->whereNotNull('total_price')->where('total_price', '>', 0);

        return [
            'total_count' => $count,
            'maker_count' => $mfrIds->count(),
            'model_count' => DB::table('bike_models')->whereIn('manufacturer_id', $mfrIds)->count(),
            'avg_price' => $count > 0 ? round((float) $prices->avg('total_price') / 10000, 1) : 0,
            'min_price' => $count > 0 ? round((float) ($prices->min('total_price') ?: 0) / 10000, 1) : 0,
            'max_price' => $count > 0 ? round((float) ($prices->max('total_price') ?: 0) / 10000, 1) : 0,
        ];
    }

    private function getMakerCards(Collection $manufacturers): Collection
    {
        return $manufacturers->map(function ($mfr) {
            $listings = Listing::where('is_sold_out', false)
                ->whereHas('bikeModel', fn ($q) => $q->where('manufacturer_id', $mfr->id));

            $count = $listings->count();
            $prices = (clone $listings)->whereNotNull('total_price')->where('total_price', '>', 0);

            $topModels = DB::table('listings')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->where('bike_models.manufacturer_id', $mfr->id)
                ->where('listings.is_sold_out', false)
                ->select('bike_models.name', DB::raw('COUNT(*) as cnt'))
                ->groupBy('bike_models.name')
                ->orderByDesc('cnt')
                ->limit(3)
                ->pluck('name')
                ->toArray();

            // 代表画像: 最新listing の画像1枚目
            $heroListing = Listing::where('is_sold_out', false)
                ->whereHas('bikeModel', fn ($q) => $q->where('manufacturer_id', $mfr->id))
                ->whereNotNull('image_urls')
                ->orderByDesc('created_at')
                ->first();
            $heroImage = $heroListing?->images[0] ?? null;

            return [
                'id' => $mfr->id,
                'name' => $mfr->name,
                'slug' => $mfr->slug,
                'country' => $mfr->country,
                'count' => $count,
                'min_price' => $count > 0 ? round((float) ($prices->min('total_price') ?: 0) / 10000, 1) : null,
                'max_price' => $count > 0 ? round((float) ($prices->max('total_price') ?: 0) / 10000, 1) : null,
                'top_models' => $topModels,
                'image_url' => $heroImage,
            ];
        })->filter(fn ($card) => $card['count'] > 0)
          ->sortByDesc('count')
          ->values();
    }

    private function getMakerStats(Manufacturer $manufacturer): array
    {
        $query = Listing::where('is_sold_out', false)
            ->whereHas('bikeModel', fn ($q) => $q->where('manufacturer_id', $manufacturer->id));

        $count = $query->count();
        $prices = (clone $query)->whereNotNull('total_price')->where('total_price', '>', 0);
        $mileage = (clone $query)->whereNotNull('mileage')->where('mileage', '>', 0);

        $priceValues = $prices->pluck('total_price')->sort()->values();
        $median = $priceValues->count() > 0
            ? round((float) $priceValues->median() / 10000, 1)
            : 0;

        return [
            'total_count' => $count,
            'avg_price' => $count > 0 ? round((float) $prices->avg('total_price') / 10000, 1) : 0,
            'median_price' => $median,
            'min_price' => $count > 0 ? round((float) ($prices->min('total_price') ?: 0) / 10000, 1) : 0,
            'max_price' => $count > 0 ? round((float) ($prices->max('total_price') ?: 0) / 10000, 1) : 0,
            'avg_mileage' => $mileage->count() > 0 ? round((float) $mileage->avg('mileage')) : null,
        ];
    }

    private function getTopModels(Manufacturer $manufacturer, int $limit): Collection
    {
        return DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->where('bike_models.manufacturer_id', $manufacturer->id)
            ->where('listings.is_sold_out', false)
            ->whereNotNull('bike_models.slug')
            ->where('bike_models.slug', '!=', '')
            ->whereNotNull('manufacturers.slug')
            ->where('manufacturers.slug', '!=', '')
            ->select(
                'bike_models.id',
                'bike_models.name',
                'bike_models.slug as model_slug',
                'manufacturers.slug as mfr_slug',
                DB::raw('COUNT(*) as listing_count'),
                DB::raw('ROUND(AVG(listings.total_price) / 10000, 1) as avg_price'),
            )
            ->groupBy('bike_models.id', 'bike_models.name', 'bike_models.slug', 'manufacturers.slug')
            ->orderByDesc('listing_count')
            ->limit($limit)
            ->get();
    }

    private function getPriceDistribution(Collection $mfrIds): array
    {
        $ranges = [
            ['label' => '〜50万', 'min' => 0, 'max' => 500000],
            ['label' => '50〜100万', 'min' => 500000, 'max' => 1000000],
            ['label' => '100〜200万', 'min' => 1000000, 'max' => 2000000],
            ['label' => '200〜300万', 'min' => 2000000, 'max' => 3000000],
            ['label' => '300万〜', 'min' => 3000000, 'max' => 999999999],
        ];

        $result = [];
        foreach ($ranges as $range) {
            $count = Listing::where('is_sold_out', false)
                ->whereHas('bikeModel', fn ($q) => $q->whereIn('manufacturer_id', $mfrIds))
                ->whereNotNull('total_price')
                ->where('total_price', '>=', $range['min'])
                ->where('total_price', '<', $range['max'])
                ->count();
            $result[] = ['label' => $range['label'], 'count' => $count];
        }

        return $result;
    }

    private function getYearDistribution(Manufacturer $manufacturer): array
    {
        return DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('bike_models.manufacturer_id', $manufacturer->id)
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.model_year')
            ->where('listings.model_year', '>=', 2000)
            ->select(
                DB::raw('FLOOR(listings.model_year / 5) * 5 as year_group'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year_group')
            ->orderBy('year_group')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->year_group . '〜' . ($row->year_group + 4) . '年',
                'count' => $row->count,
            ])
            ->toArray();
    }

    private function getNewArrivals(Collection $mfrIds, int $limit): Collection
    {
        return Listing::where('is_sold_out', false)
            ->whereHas('bikeModel', fn ($q) => $q->whereIn('manufacturer_id', $mfrIds))
            ->with(['bikeModel.manufacturer'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function getOtherMakers(int $excludeId): Collection
    {
        return Manufacturer::whereNotIn('name', self::DOMESTIC_NAMES)
            ->where('id', '!=', $excludeId)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('bikeModels.listings', fn ($q) => $q->where('is_sold_out', false))
            ->withCount(['bikeModels as listings_count' => function ($q) {
                $q->whereHas('listings', fn ($lq) => $lq->where('is_sold_out', false));
            }])
            ->orderByDesc('listings_count')
            ->limit(10)
            ->get();
    }
}
