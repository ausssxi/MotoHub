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

        // 在庫総数（title / meta / 見出し / JSON-LD / noindex判定で共用する正本）を1回だけCOUNT。
        // getByShopId は simplePaginate のため total() を持たず、PaginationFormatter も total=0 を返す。
        // 旧実装は count()（現在ページ件数=perPage）に頭打ちで実数より小さく表示されていたため、
        // 実在庫数を pagination.total に上書きして全表示箇所で正確な台数を使う。
        // （last_page/pages は lastPage() 由来で total とは独立のためページネーション動作に影響しない）
        $stockCount = Listing::where('shop_id', $shopId)->active()->count();
        $pagination['total'] = $stockCount;

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
        // 在庫検索意図のクリックを後押しするため、在庫台数を前段に配置（在庫0台は対象外＝文を出さない）
        $description = '';
        if ($stockCount > 0) {
            $description = '現在' . number_format($stockCount) . '台の中古バイクを掲載中。';
        }
        $description .= $shop->name . 'は' . $shop->prefecture . ($shop->city ?? '') . 'のバイクショップです。';
        if ($shop->business_hours && $shop->business_hours !== '-') {
            $description .= '営業時間は' . $shop->business_hours . '。';
        }
        $description .= '在庫・営業時間・アクセスを掲載しています。';

        return [
            'shop' => $shop,
            'description' => $description,
            'stockCount' => $stockCount,
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
        $shops = $this->shopRepo->findInBounds(
            (float)$coords['sw_lat'],
            (float)$coords['sw_lng'],
            (float)$coords['ne_lat'],
            (float)$coords['ne_lng']
        );

        // マップのピン分類を付与: chain（チェーン別）→ maker_dealer（正規店バッジ・チェーン非該当のみ）→ その他(null)。
        // service_tags は判定に使うのみでクライアントには送らない（payload肥大・生バッジ非公開）。
        $shops->each(function ($shop) {
            $chain = Shop::chainSlug($shop->name);
            $shop->setAttribute('chain', $chain);
            $shop->setAttribute('maker_dealer', Shop::makerDealer($shop->service_tags, $chain));
            $shop->makeHidden('service_tags');
        });

        return $shops;
    }
}