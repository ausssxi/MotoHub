<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Shop;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Bike\ListingRepository;
use App\Services\Bike\Search\PaginationFormatter;
use App\Services\NearbyService;
use App\Http\Resources\Bike\ListingResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 店舗関連のビジネスロジック
 */
final class ShopService
{
    public function __construct(
        private readonly ShopRepository $shopRepo,
        private readonly ListingRepository $listingRepo,
        private readonly PaginationFormatter $paginator,
        private readonly NearbyService $nearbyService
    ) {}

    /**
     * 店舗詳細ページ用のデータを取得
     */
    public function getShopDetailWithListings(int $shopId): array
    {
        $shop = $this->shopRepo->findOrFail($shopId);

        $paginated = $this->listingRepo->getByShopId($shopId, 20);
        $pagination = $this->paginator->format($paginated);

        // ★追加: PaginationFormatterがtotalを落としてしまう問題の対策
        // ページネーターが total() メソッドを持っていれば、正確な「全在庫数」を強制上書きする
        if (method_exists($paginated, 'total')) {
            $pagination['total'] = $paginated->total();
        } elseif (!isset($pagination['total']) || $pagination['total'] === 0) {
            // simplePaginate等の場合は、とりあえず今取得できている件数を入れる
            $pagination['total'] = $paginated->count();
        }

        // 取扱メーカー集計（在庫がある車両のメーカーを台数順で取得）
        $manufacturers = Listing::where('shop_id', $shopId)
            ->where('is_sold_out', false)
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->select('manufacturers.id', 'manufacturers.name', 'manufacturers.slug', DB::raw('COUNT(*) as stock_count'))
            ->groupBy('manufacturers.id', 'manufacturers.name', 'manufacturers.slug')
            ->orderByDesc('stock_count')
            ->get();

        // 近くの駐車場・ショップ・回遊リンク
        $nearbyParkings = collect();
        $nearbyShops = collect();
        if ($shop->latitude && $shop->longitude) {
            $nearbyParkings = $this->nearbyService->getNearbyParkings((float) $shop->latitude, (float) $shop->longitude);
            $nearbyShops = $this->nearbyService->getNearbyShops((float) $shop->latitude, (float) $shop->longitude, $shop->id);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'car', 'description' => '愛車を登録・管理'],
        ];

        $shopExpensesStats = $this->getShopExpensesStats($shop);

        // 店舗説明文（meta description / JSON-LD / ページ表示で共用）
        $description = $shop->name . 'は' . $shop->prefecture . 'にあるバイクショップです。';
        if ($shop->chain) {
            $description .= $shop->chain->name . 'は全国に展開する大手バイク販売チェーンで、新車・中古車の販売、買取、車検、整備に対応しています。';
        }
        if ($shop->business_hours && $shop->business_hours !== '-') {
            $description .= '営業時間は' . $shop->business_hours . '。';
        }
        $description .= 'お気軽にお問い合わせください。';

        return [
            'shop' => $shop,
            'description' => $description,
            'items' => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination' => $pagination,
            'manufacturers' => $manufacturers,
            'nearbyParkings' => $nearbyParkings,
            'nearbyShops' => $nearbyShops,
            'crossLinks' => $crossLinks,
            'shopExpensesStats' => $shopExpensesStats,
        ];
    }

    /**
     * ショップの諸経費統計を算出
     */
    private function getShopExpensesStats(Shop $shop): ?array
    {
        $stats = Listing::where('shop_id', $shop->id)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->whereNotNull('price')
            ->whereRaw('total_price > price')
            ->selectRaw('
                AVG(total_price - price) as avg_expenses,
                MIN(total_price - price) as min_expenses,
                MAX(total_price - price) as max_expenses,
                COUNT(*) as count
            ')
            ->first();

        if (!$stats || $stats->count < 3) {
            return null;
        }

        $nationalAvg = Cache::remember('national_avg_expenses', 3600, function () {
            return Listing::where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->whereNotNull('price')
                ->whereRaw('total_price > price')
                ->avg(DB::raw('total_price - price'));
        });

        if (!$nationalAvg) {
            return null;
        }

        $diff = (float) $stats->avg_expenses - (float) $nationalAvg;
        $diffPercent = ($diff / (float) $nationalAvg) * 100;

        $barPosition = (int) max(1, min(10, round(5 + ($diffPercent / 10))));

        if ($diffPercent <= -20) {
            $evaluation = ['icon' => 'check', 'text' => '諸経費がかなり安い', 'color' => 'green'];
        } elseif ($diffPercent <= -5) {
            $evaluation = ['icon' => 'check', 'text' => '諸経費が安め', 'color' => 'green'];
        } elseif ($diffPercent <= 5) {
            $evaluation = ['icon' => 'minus', 'text' => '全国平均並み', 'color' => 'gray'];
        } elseif ($diffPercent <= 20) {
            $evaluation = ['icon' => 'alert-triangle', 'text' => '諸経費がやや高め', 'color' => 'orange'];
        } else {
            $evaluation = ['icon' => 'alert-triangle', 'text' => '諸経費が高め', 'color' => 'orange'];
        }

        return [
            'avg' => (int) $stats->avg_expenses,
            'min' => (int) $stats->min_expenses,
            'max' => (int) $stats->max_expenses,
            'count' => (int) $stats->count,
            'nationalAvg' => (int) $nationalAvg,
            'diff' => (int) $diff,
            'diffPercent' => round($diffPercent, 1),
            'barPosition' => $barPosition,
            'evaluation' => $evaluation,
        ];
    }

    /**
     * 地図エリア検索用の店舗データを取得
     */
    public function getShopsInArea(array $coords): Collection
    {
        return $this->shopRepo->findInBounds(
            (float)$coords['sw_lat'],
            (float)$coords['sw_lng'],
            (float)$coords['ne_lat'],
            (float)$coords['ne_lng']
        );
    }
}