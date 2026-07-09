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
            ->approved() // 公開(is_approved=true)のみ。承認待ち・通報非公開は出さない
            ->with('user') // 公開表示名(display_name)のフォールバックで使用＝N+1防止
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(int $parkingId, array $data): ParkingReview
    {
        $data['bike_parking_id'] = $parkingId;

        return ParkingReview::create($data);
    }
}
