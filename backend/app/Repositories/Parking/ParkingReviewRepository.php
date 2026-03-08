<?php

declare(strict_types=1);

namespace App\Repositories\Parking;

use App\Models\ParkingReview;
use Illuminate\Database\Eloquent\Collection;

final class ParkingReviewRepository
{
    public function getByParking(int $parkingId): Collection
    {
        return ParkingReview::where('bike_parking_id', $parkingId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(int $parkingId, array $data): ParkingReview
    {
        $data['bike_parking_id'] = $parkingId;

        return ParkingReview::create($data);
    }
}
