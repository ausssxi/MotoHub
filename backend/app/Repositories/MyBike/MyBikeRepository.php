<?php

declare(strict_types=1);

namespace App\Repositories\MyBike;

use App\Models\MyBike;
use App\Models\FuelLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * 愛車データのデータベース操作を担当
 */
final class MyBikeRepository
{
    /**
     * ログインユーザーの愛車一覧を取得
     */
    public function getByUser(int $userId): Collection
    {
        return MyBike::where('user_id', $userId)
            ->with('bikeModel')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 愛車を新規登録
     */
    public function create(int $userId, array $data): MyBike
    {
        return MyBike::create(array_merge($data, ['user_id' => $userId]));
    }

    /**
     * 愛車詳細を取得（権限チェック用）
     */
    public function findOrFail(int $myBikeId): MyBike
    {
        return MyBike::findOrFail($myBikeId);
    }

    /**
     * 指定日より前の直近の給油ログを取得（燃費計算用）
     */
    public function getPreviousFuelLog(int $myBikeId, string $date): ?FuelLog
    {
        return FuelLog::where('my_bike_id', $myBikeId)
            ->where('filled_at', '<', $date)
            ->orderBy('filled_at', 'desc')
            ->first();
    }

    /**
     * 給油ログを作成
     */
    public function createFuelLog(MyBike $myBike, array $data): FuelLog
    {
        return $myBike->fuelLogs()->create($data);
    }

    /**
     * 整備ログを作成
     */
    public function createMaintenanceLog(MyBike $myBike, array $data): void
    {
        $myBike->maintenanceLogs()->create($data);
    }

    /**
     * 走行距離を更新（入力値が大きい場合のみ）
     */
    public function updateOdometerIfGreater(MyBike $myBike, int $newOdometer): void
    {
        if ($newOdometer > $myBike->odometer) {
            $myBike->update(['odometer' => $newOdometer]);
        }
    }
}