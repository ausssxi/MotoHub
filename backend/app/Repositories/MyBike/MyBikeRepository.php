<?php

declare(strict_types=1);

namespace App\Repositories\MyBike;

use App\Models\MyBike;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * 愛車データのデータベース操作を担当
 */
final class MyBikeRepository
{
    /**
     * ログインユーザーの愛車一覧を取得
     * (車種情報と最新の給油履歴5件も一緒に取得)
     */
    public function getByUser(User $user, int $logLimit = 5): Collection
    {
        return $user->myBikes()
            ->with(['bikeModel.manufacturer', 'fuelLogs' => function ($q) use ($logLimit) {
                $q->limit($logLimit);
            }])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * IDで愛車を取得（所有者チェック込み）
     * 詳細ページ用
     */
    public function findByUserAndIdOrFail(User $user, int $id): MyBike
    {
        return $user->myBikes()
            ->with(['bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs', 'images'])
            ->findOrFail($id);
    }

    /**
     * 愛車を新規登録
     */
    public function create(User $user, array $data): MyBike
    {
        return $user->myBikes()->create($data);
    }

    /**
     * 愛車を削除
     */
    public function delete(MyBike $myBike): ?bool
    {
        return $myBike->delete();
    }

    /**
     * 走行距離を更新（入力値が大きい場合のみ）
     * ※カラム名は current_odometer に合わせています
     */
    public function updateOdometerIfGreater(MyBike $myBike, float $newOdometer): void
    {
        if ($newOdometer > $myBike->current_odometer) {
            $myBike->update(['current_odometer' => $newOdometer]);
        }
    }
}
