<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeParking;
use App\Models\User;
use App\Repositories\Parking\BikeParkingRepository;
use App\Repositories\Parking\ParkingReviewRepository;
use Illuminate\Database\Eloquent\Collection;

final class ParkingService
{
    public function __construct(
        private readonly BikeParkingRepository $parkingRepo,
        private readonly ParkingReviewRepository $reviewRepo
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

        return [
            'parking' => $parking,
            'reviews' => $reviews,
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
