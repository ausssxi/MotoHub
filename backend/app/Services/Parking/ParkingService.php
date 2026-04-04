<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeParking;
use App\Models\BikeParkingImage;
use App\Models\Listing;
use App\Models\User;
use App\Repositories\Parking\BikeParkingRepository;
use App\Repositories\Parking\ParkingReviewRepository;
use App\Services\NearbyService;
use Illuminate\Database\Eloquent\Collection;
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
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'garage', 'description' => '愛車を登録・管理'],
        ];

        // このエリアで売っているバイク
        $nearbyListings = collect();
        if ($parking->prefecture) {
            $nearbyListings = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $parking->prefecture))
                ->where('is_sold_out', 0)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        }

        return [
            'parking' => $parking,
            'reviews' => $reviews,
            'nearbyParkings' => $nearbyParkings,
            'nearbyShops' => $nearbyShops,
            'nearbyListings' => $nearbyListings,
            'crossLinks' => $crossLinks,
        ];
    }

    public function registerParking(?User $user, array $data): BikeParking
    {
        $images = $data['images'] ?? [];
        unset($data['images']);

        $parking = $this->parkingRepo->create($user, $data);

        // 画像保存
        if (!empty($images)) {
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
            if (!isset($data[$field])) {
                $data[$field] = false;
            }
        }

        $parking->update($data);

        // 画像削除
        if (!empty($deleteImages)) {
            $imagesToDelete = $parking->images()->whereIn('id', $deleteImages)->get();
            foreach ($imagesToDelete as $img) {
                Storage::disk('public')->delete($img->image_path);
                $img->delete();
            }
        }

        // 新しい画像追加
        if (!empty($images)) {
            $currentCount = $parking->images()->count();
            $this->storeImages($parking, $images, $user, $currentCount);
        }

        return $parking->fresh();
    }

    public function addReview(int $parkingId, ?User $user, array $data): void
    {
        if ($user) {
            $data['user_id'] = $user->id;
        }

        $this->reviewRepo->create($parkingId, $data);

        // avg_rating と reviews_count を再計算
        $parking = $this->parkingRepo->findOrFail($parkingId);
        $parking->update([
            'avg_rating' => $parking->reviews()->avg('rating'),
            'reviews_count' => $parking->reviews()->count(),
        ]);
    }
}
