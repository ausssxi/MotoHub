<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeParking;
use App\Models\User;
use App\Repositories\Parking\BikeParkingRepository;
use App\Repositories\Parking\ParkingReviewRepository;
use App\Services\NearbyService;
use Illuminate\Database\Eloquent\Collection;

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

        return [
            'parking' => $parking,
            'reviews' => $reviews,
            'nearbyParkings' => $nearbyParkings,
            'nearbyShops' => $nearbyShops,
            'crossLinks' => $crossLinks,
        ];
    }

    public function registerParking(?User $user, array $data): BikeParking
    {
        return $this->parkingRepo->create($user, $data);
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
