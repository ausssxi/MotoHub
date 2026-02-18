<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Models\User;
use App\Models\MyBike;
use App\Repositories\MyBike\MyBikeRepository;
use App\Repositories\MyBike\FuelLogRepository;
use App\Repositories\MyBike\MaintenanceLogRepository;
use App\Repositories\Bike\BikeModelRepository;

final class MyBikeService
{
    public function __construct(
        private readonly MyBikeRepository $myBikeRepo,
        private readonly FuelLogRepository $fuelLogRepo,
        private readonly MaintenanceLogRepository $maintenanceLogRepo,
        private readonly BikeModelRepository $bikeModelRepo
    ) {}

    /**
     * ガレージ一覧データを取得
     */
    public function getGarageData(User $user)
    {
        return $this->myBikeRepo->getByUser($user);
    }

    /**
     * 愛車詳細データを取得
     */
    public function getBikeDetail(User $user, int $id): MyBike
    {
        return $this->myBikeRepo->findByUserAndIdOrFail($user, $id);
    }

    /**
     * 愛車を登録
     */
    public function registerBike(User $user, array $data): MyBike
    {
        $data['current_odometer'] = $data['initial_odometer'] ?? 0;
        $data['initial_odometer'] = $data['initial_odometer'] ?? 0;
        
        return $this->myBikeRepo->create($user, $data);
    }

    /**
     * 愛車を削除
     */
    public function deleteBike(MyBike $myBike): void
    {
        $this->myBikeRepo->delete($myBike);
    }

    /**
     * 給油を記録する（燃費計算ロジック込み）
     */
    public function recordFuel(MyBike $myBike, array $data): void
    {
        // 今回のオドメーターを数値化
        $currentOdometer = (float)$data['odometer'];

        // 燃費計算ロジック
        if (!empty($data['is_full_tank']) && $data['is_full_tank']) {
            // 1. 直近の「満タン給油」を探す
            $prevFullLog = $this->fuelLogRepo->findLatestFullLogBefore($myBike, $currentOdometer);

            if ($prevFullLog) {
                // DBの値を数値化 (ここがエラーの原因でした)
                $prevOdometer = (float)$prevFullLog->odometer;

                // 2. その間の「ちょい足し給油」の量を合計
                $additions = $this->fuelLogRepo->sumQuantityBetween(
                    $myBike, 
                    $prevOdometer, 
                    $currentOdometer
                );

                // 3. 総消費量
                $currentQuantity = (float)$data['quantity'];
                $totalQuantity = $currentQuantity + $additions;
                
                // 4. 走行距離の差分
                $distance = $currentOdometer - $prevOdometer;

                // 5. 燃費計算
                if ($distance > 0 && $totalQuantity > 0) {
                    $data['efficiency'] = round($distance / $totalQuantity, 2);
                }
            }
        }

        // 保存（計算された efficiency もここで $data に入っているため保存されます）
        $this->fuelLogRepo->create($myBike, $data);

        // オドメーター更新
        $this->myBikeRepo->updateOdometerIfGreater($myBike, $currentOdometer);
    }

    /**
     * 整備を記録する
     */
    public function recordMaintenance(MyBike $myBike, array $data): void
    {
        $this->maintenanceLogRepo->create($myBike, $data);

        if (!empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float)$data['odometer']);
        }
    }

    /**
     * 車種を検索する
     */
    public function searchModels(string $keyword): array
    {
        $models = $this->bikeModelRepo->searchByName($keyword, 20);
        
        return $models->map(fn($m) => [
            'id' => $m->id,
            'text' => "{$m->manufacturer->name} {$m->name}",
            'image' => $m->image_url
        ])->toArray();
    }
}