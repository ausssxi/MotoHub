<?php

declare(strict_types=1);

namespace App\Repositories\MyBike;

use App\Models\FuelLog;
use App\Models\MyBike;

final class FuelLogRepository
{
    /**
     * 給油記録を作成
     */
    public function create(MyBike $myBike, array $data): FuelLog
    {
        return $myBike->fuelLogs()->create($data);
    }

    /**
     * 指定した距離より前にある、直近の満タン給油記録を取得
     * （燃費計算の基準点を探す用）
     */
    public function findLatestFullLogBefore(MyBike $myBike, float $currentOdometer): ?FuelLog
    {
        return $myBike->fuelLogs()
            ->where('odometer', '<', $currentOdometer)
            ->where('is_full_tank', true)
            ->orderBy('odometer', 'desc')
            ->first();
    }

    /**
     * 2つのオドメーター間の給油量合計を取得
     * （ちょい足し給油の合算用）
     */
    public function sumQuantityBetween(MyBike $myBike, float $startOdometer, float $endOdometer): float
    {
        return (float) $myBike->fuelLogs()
            ->where('odometer', '>', $startOdometer)
            ->where('odometer', '<', $endOdometer)
            ->sum('quantity');
    }
}