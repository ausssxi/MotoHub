<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Repositories\MyBike\MyBikeRepository;
use App\Models\MyBike;
use Exception;

/**
 * 愛車管理のビジネスロジック
 */
final class MyBikeService
{
    public function __construct(
        private readonly MyBikeRepository $repository
    ) {}

    /**
     * ユーザーの愛車一覧を取得
     */
    public function getUserBikes(int $userId)
    {
        return $this->repository->getByUser($userId);
    }

    /**
     * 愛車を登録
     */
    public function registerBike(int $userId, array $data): void
    {
        $this->repository->create($userId, $data);
    }

    /**
     * 愛車の詳細情報を取得（グラフデータ含む）
     */
    public function getBikeDetail(int $myBikeId, int $userId): array
    {
        $myBike = $this->repository->findOrFail($myBikeId);

        // 権限チェック
        if ($myBike->user_id !== $userId) {
            abort(403);
        }

        $myBike->load(['fuelLogs', 'maintenanceLogs']);

        // 燃費チャート用のデータ作成
        $chartData = [
            'labels' => $myBike->fuelLogs->pluck('filled_at')->map(fn($d) => $d->format('m/d'))->reverse()->values(),
            'data' => $myBike->fuelLogs->pluck('efficiency')->reverse()->values(),
        ];

        return compact('myBike', 'chartData');
    }

    /**
     * 給油を記録して燃費を計算
     */
    public function recordFuel(int $myBikeId, int $userId, array $data): void
    {
        $myBike = $this->repository->findOrFail($myBikeId);
        
        if ($myBike->user_id !== $userId) {
            abort(403);
        }

        // 燃費計算ロジック
        // (今回距離 - 前回距離) / 給油量
        $prevLog = $this->repository->getPreviousFuelLog($myBikeId, $data['filled_at']);
        $efficiency = null;

        if ($prevLog && $data['odometer'] > $prevLog->odometer) {
            $distance = $data['odometer'] - $prevLog->odometer;
            $efficiency = $distance / $data['quantity'];
        }

        // ログ保存
        $this->repository->createFuelLog($myBike, array_merge($data, ['efficiency' => $efficiency]));

        // 総走行距離の更新
        $this->repository->updateOdometerIfGreater($myBike, (int)$data['odometer']);
    }

    /**
     * 整備を記録
     */
    public function recordMaintenance(int $myBikeId, int $userId, array $data): void
    {
        $myBike = $this->repository->findOrFail($myBikeId);

        if ($myBike->user_id !== $userId) {
            abort(403);
        }

        $this->repository->createMaintenanceLog($myBike, $data);

        if (isset($data['odometer'])) {
            $this->repository->updateOdometerIfGreater($myBike, (int)$data['odometer']);
        }
    }
}