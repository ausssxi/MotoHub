<?php

declare(strict_types=1);

namespace App\Repositories\Parking;

use App\Models\BikeParking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BikeParkingRepository
{
    public function findOrFail(int $id): BikeParking
    {
        return BikeParking::active()->findOrFail($id);
    }

    public function findInBounds(float $swLat, float $swLng, float $neLat, float $neLng, ?string $parkingType = null, int $limit = 200): Collection
    {
        $query = BikeParking::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'prefecture', 'parking_type', 'price_per_hour', 'price_per_day', 'price_per_month', 'is_free', 'avg_rating', 'reviews_count', 'is_covered', 'is_locked', 'capacity', 'available_hours', 'price_detail'])
            ->active()
            ->inBounds($swLat, $swLng, $neLat, $neLng);

        if ($parkingType) {
            $query->where('parking_type', $parkingType);
        }

        return $query->limit($limit)->get();
    }

    public function getByPrefecture(string $prefecture): Collection
    {
        return BikeParking::active()
            ->byPrefecture($prefecture)
            ->orderByDesc('avg_rating')
            ->get();
    }

    public function create(?User $user, array $data): BikeParking
    {
        if ($user) {
            $data['user_id'] = $user->id;
        }

        return BikeParking::create($data);
    }
}
