<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeModel;
use App\Models\BikeParking;
use App\Models\BikeParkingImage;
use App\Models\Listing;
use App\Models\TouringSpot;
use App\Models\User;
use App\Repositories\Parking\BikeParkingRepository;
use App\Repositories\Parking\ParkingReviewRepository;
use App\Services\NearbyService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class ParkingService
{
    public function __construct(
        private readonly BikeParkingRepository $parkingRepo,
        private readonly ParkingReviewRepository $reviewRepo,
        private readonly NearbyService $nearbyService
    ) {}

    public function getParkingsInArea(array $coords, ?string $parkingType = null): Collection
    {
        return $this->parkingRepo->findInBounds(
            (float) $coords['sw_lat'],
            (float) $coords['sw_lng'],
            (float) $coords['ne_lat'],
            (float) $coords['ne_lng'],
            $parkingType
        );
    }

    public function getParkingDetail(int $id): array
    {
        $parking = $this->parkingRepo->findOrFail($id);
        $parking->load('images');
        $reviews = $this->reviewRepo->getByParking($id);

        $nearbyParkings = collect();
        $nearbyShops = collect();
        if ($parking->latitude && $parking->longitude) {
            $nearbyParkings = $this->nearbyService->getNearbyParkings((float) $parking->latitude, (float) $parking->longitude, $parking->id);
            $nearbyShops = $this->nearbyService->getNearbyShops((float) $parking->latitude, (float) $parking->longitude);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '他の駐車場を探す'],
            ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'car', 'description' => '愛車を登録・管理'],
        ];

        // このエリアで売っているバイク（市区町村レベルに絞り込み、なければ都道府県）
        $nearbyListings = collect();
        if ($parking->city && $parking->prefecture) {
            $nearbyListings = Listing::whereHas('shop', fn ($q) => $q->where('prefecture', $parking->prefecture)->where('address', 'like', '%'.$parking->city.'%'))
                ->where('is_sold_out', 0)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        }
        if ($nearbyListings->isEmpty() && $parking->prefecture) {
            $nearbyListings = Listing::whereHas('shop', fn ($q) => $q->where('prefecture', $parking->prefecture))
                ->where('is_sold_out', 0)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        }

        // 排気量制限を解析
        $displacementLimit = $this->parseDisplacementLimit($parking);

        // 入庫可能バイク判定サンプル
        $bikeCompatibility = $this->getBikeCompatibility($displacementLimit);

        // 近隣駐車場の料金比較（1km圏）
        $priceComparison = $this->getPriceComparison($parking);

        // 駅情報をロード
        $parking->load('station');

        // 周辺ツーリングスポット（同一都道府県から最大5件）
        $touringSpots = collect();
        if ($parking->prefecture) {
            $touringSpots = TouringSpot::where('prefecture', $parking->prefecture)
                ->orderBy('name')
                ->limit(5)
                ->get(['name', 'slug', 'prefecture']);
        }

        return [
            'parking' => $parking,
            'reviews' => $reviews,
            'nearbyParkings' => $nearbyParkings,
            'nearbyShops' => $nearbyShops,
            'nearbyListings' => $nearbyListings,
            'crossLinks' => $crossLinks,
            'displacementLimit' => $displacementLimit,
            'bikeCompatibility' => $bikeCompatibility,
            'priceComparison' => $priceComparison,
            'touringSpots' => $touringSpots,
        ];
    }

    public function registerParking(?User $user, array $data): BikeParking
    {
        $images = $data['images'] ?? [];
        unset($data['images']);

        $parking = $this->parkingRepo->create($user, $data);

        // 画像保存
        if (! empty($images)) {
            $this->storeImages($parking, $images, $user);
        }

        return $parking;
    }

    private function storeImages(BikeParking $parking, array $images, ?User $user, int $startOrder = 0): void
    {
        $dir = "parking/user-images/{$parking->id}";

        foreach ($images as $index => $image) {
            $path = $image->store($dir, 'public');

            BikeParkingImage::create([
                'bike_parking_id' => $parking->id,
                'image_path' => $path,
                'user_id' => $user?->id,
                'sort_order' => $startOrder + $index,
            ]);
        }
    }

    public function updateParking(BikeParking $parking, ?User $user, array $data): BikeParking
    {
        $images = $data['images'] ?? [];
        $deleteImages = $data['delete_images'] ?? [];
        unset($data['images'], $data['delete_images']);

        // チェックボックス未送信時はfalseにする
        foreach (['is_free', 'is_covered', 'is_locked', 'has_security_camera', 'available_24h'] as $field) {
            if (! isset($data[$field])) {
                $data[$field] = false;
            }
        }

        $parking->update($data);

        // 画像削除
        if (! empty($deleteImages)) {
            $imagesToDelete = $parking->images()->whereIn('id', $deleteImages)->get();
            foreach ($imagesToDelete as $img) {
                Storage::disk('public')->delete($img->image_path);
                $img->delete();
            }
        }

        // 新しい画像追加
        if (! empty($images)) {
            $currentCount = $parking->images()->count();
            $this->storeImages($parking, $images, $user, $currentCount);
        }

        return $parking->fresh();
    }

    /**
     * 排気量制限をテキストから自動抽出
     *
     * @return array{min: int|null, max: int|null, label: string|null}
     */
    public function parseDisplacementLimit(BikeParking $parking): array
    {
        $result = ['min' => null, 'max' => null, 'label' => null];
        $texts = array_filter([$parking->vehicle_restriction, $parking->price_detail, $parking->notes]);
        $combined = implode(' ', $texts);

        if (empty($combined)) {
            return $result;
        }

        // "50cc以下不可" "原付不可" → min=51
        if (preg_match('/50\s*cc\s*(以下\s*)?(不可|禁止|×|NG)/u', $combined) || preg_match('/原付\s*(不可|禁止|×|NG)/u', $combined)) {
            $result['min'] = 51;
            $result['label'] = '50cc以下不可';
        }
        // "125cc以下のみ" "125ccまで" → max=125
        if (preg_match('/(\d+)\s*cc\s*(以下\s*(のみ|限定|まで)|まで)/u', $combined, $m)) {
            $result['max'] = (int) $m[1];
            $result['label'] = ($result['label'] ? $result['label'].' / ' : '')."{$m[1]}cc以下のみ";
        }
        // "大型不可" "大型バイク不可" → max=400
        if (preg_match('/大型\s*(バイク\s*)?(不可|禁止|×|NG)/u', $combined)) {
            $result['max'] = 400;
            $result['label'] = ($result['label'] ? $result['label'].' / ' : '').'大型不可';
        }
        // "排気量制限なし" "全排気量OK"
        if (preg_match('/(排気量\s*制限\s*な|全排気量|排気量\s*不問)/u', $combined)) {
            $result['label'] = '排気量制限なし';
        }

        return $result;
    }

    /**
     * 入庫可能バイクの判定サンプルを返す
     */
    private function getBikeCompatibility(array $displacementLimit): array
    {
        return Cache::remember('parking_bike_compat_samples', 86400, function () {
            // 代表的なバイクを排気量別に取得
            $sampleNames = ['PCX', 'スーパーカブ110', 'レブル250', 'Ninja400', 'CB400SF', 'Z900RS', 'ハヤブサ'];

            return BikeModel::whereIn('name', $sampleNames)
                ->select('id', 'name', 'displacement', 'slug')
                ->get()
                ->map(fn (BikeModel $m) => [
                    'name' => $m->name,
                    'displacement' => $m->displacement,
                    'slug' => $m->slug,
                ])
                ->sortBy('displacement')
                ->values()
                ->toArray();
        });
    }

    /**
     * 半径1km以内の駐車場と料金を比較
     */
    private function getPriceComparison(BikeParking $parking): array
    {
        if (! $parking->latitude || ! $parking->longitude) {
            return [];
        }

        // 約1km = 緯度±0.009, 経度±0.011
        $nearbyForCompare = BikeParking::active()
            ->where('id', '!=', $parking->id)
            ->inBounds(
                $parking->latitude - 0.009,
                $parking->longitude - 0.011,
                $parking->latitude + 0.009,
                $parking->longitude + 0.011
            )
            ->select(['id', 'name', 'price_per_hour', 'price_per_day', 'price_per_month', 'is_free', 'latitude', 'longitude'])
            ->limit(5)
            ->get();

        return $nearbyForCompare->map(function (BikeParking $p) use ($parking) {
            $dist = $this->haversineDistance($parking->latitude, $parking->longitude, $p->latitude, $p->longitude);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'price_per_hour' => $p->price_per_hour,
                'price_per_month' => $p->price_per_month,
                'is_free' => $p->is_free,
                'distance_m' => (int) round($dist),
            ];
        })->sortBy('distance_m')->values()->toArray();
    }

    /**
     * 2点間の距離をメートルで返す (Haversine)
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function addReview(int $parkingId, ?User $user, array $data): void
    {
        if ($user) {
            $data['user_id'] = $user->id;
        }

        $this->reviewRepo->create($parkingId, $data);

        $this->recomputeAggregates($parkingId);
    }

    /**
     * avg_rating / reviews_count を「公開(is_approved=true)」レビューのみで再計算。
     * 非公開化（通報対応・新規ガード）時も集計から除外されるよう、公開/非公開いずれの
     * 変化でもこのメソッドを通す。
     */
    public function recomputeAggregates(int $parkingId): void
    {
        $parking = $this->parkingRepo->findOrFail($parkingId);
        $parking->update([
            'avg_rating' => $parking->reviews()->approved()->avg('rating') ?? 0,
            'reviews_count' => $parking->reviews()->approved()->count(),
        ]);
    }
}
