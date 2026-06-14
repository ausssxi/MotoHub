<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Models\MyBike;
use App\Models\User;
use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\MyBike\FuelLogRepository;
use App\Repositories\MyBike\MaintenanceLogRepository;
use App\Repositories\MyBike\MyBikeRepository;

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
     * retention核ダッシュボード（per-bike・private・読み込み済みログから集計＝N+1なし）。
     * 維持費（累計/直近12ヶ月/年別・内訳）／燃費グラフ用データ／整備リマインダー（距離ベース）。
     *
     * @return array{cost: array, fuelChart: array, reminders: array<int, array>}
     */
    public function buildDashboard(MyBike $myBike): array
    {
        $maint = $myBike->maintenanceLogs;   // 既に eager load 済み
        $fuel = $myBike->fuelLogs;
        $oneYearAgo = now()->subYear();

        // --- 1) 維持費 ---
        $maintTotal = (int) $maint->sum('cost');
        $fuelTotal = (int) $fuel->sum('cost');
        $last12 = (int) (
            $maint->filter(fn ($m) => $m->maintained_at && $m->maintained_at->gte($oneYearAgo))->sum('cost')
            + $fuel->filter(fn ($f) => $f->filled_at && $f->filled_at->gte($oneYearAgo))->sum('cost')
        );

        $byYear = [];
        foreach ($maint as $m) {
            if ($m->maintained_at) {
                $y = $m->maintained_at->format('Y');
                $byYear[$y]['maintenance'] = ($byYear[$y]['maintenance'] ?? 0) + (int) $m->cost;
            }
        }
        foreach ($fuel as $f) {
            if ($f->filled_at) {
                $y = $f->filled_at->format('Y');
                $byYear[$y]['fuel'] = ($byYear[$y]['fuel'] ?? 0) + (int) $f->cost;
            }
        }
        krsort($byYear);
        foreach ($byYear as $y => &$row) {
            $row['maintenance'] = $row['maintenance'] ?? 0;
            $row['fuel'] = $row['fuel'] ?? 0;
            $row['total'] = $row['maintenance'] + $row['fuel'];
        }
        unset($row);

        $cost = [
            'total' => $maintTotal + $fuelTotal,
            'maintenance_total' => $maintTotal,
            'fuel_total' => $fuelTotal,
            'last12' => $last12,
            'by_year' => $byYear,
        ];

        // --- 2) 燃費グラフ（時系列昇順・平均）。ログ0でも破綻しない ---
        $effLogs = $fuel->filter(fn ($l) => $l->efficiency !== null && $l->filled_at)
            ->sortBy(fn ($l) => $l->filled_at->timestamp)
            ->values();
        $data = $effLogs->map(fn ($l) => (float) $l->efficiency)->all();
        $fuelChart = [
            'labels' => $effLogs->map(fn ($l) => $l->filled_at->format('Y/m/d'))->all(),
            'data' => $data,
            'average' => count($data) > 0 ? round(array_sum($data) / count($data), 1) : null,
        ];

        // --- 3) 整備リマインダー（距離ベース）---
        $reminders = [];
        foreach ($maint->filter(fn ($m) => $m->odometer !== null)->groupBy('title') as $title => $logs) {
            $last = $logs->sortByDesc('odometer')->first(); // 最も進んだ走行距離＝直近整備
            $lastOdo = (float) $last->odometer;
            $distance = max(0.0, (float) $myBike->current_odometer - $lastOdo);
            $guideline = $this->maintenanceGuidelineKm((string) $title);

            $reminders[] = [
                'title' => $title,
                'last_odometer' => $lastOdo,
                'last_at' => $last->maintained_at,
                'distance' => $distance,
                'guideline' => $guideline,
                'over' => $guideline !== null && $distance > $guideline,
            ];
        }
        usort($reminders, fn ($a, $b) => $b['distance'] <=> $a['distance']);

        return ['cost' => $cost, 'fuelChart' => $fuelChart, 'reminders' => $reminders];
    }

    /**
     * 整備種別名から「よくある交換目安(km)」を返す（断定でなく目安）。該当なしは null。
     * title は自由文のため部分一致で判定。
     */
    private function maintenanceGuidelineKm(string $title): ?int
    {
        if (str_contains($title, 'オイル') && (str_contains($title, 'フィルター') || str_contains($title, 'エレメント'))) {
            return 6000;
        }
        if (str_contains($title, 'オイル')) {
            return 3000;
        }
        if (str_contains($title, 'タイヤ')) {
            return 15000;
        }
        if (str_contains($title, 'ブレーキ') && (str_contains($title, 'パッド') || str_contains($title, 'シュー'))) {
            return 10000;
        }

        return null;
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
        $currentOdometer = (float) $data['odometer'];

        // 燃費計算ロジック
        if (! empty($data['is_full_tank']) && $data['is_full_tank']) {
            // 1. 直近の「満タン給油」を探す
            $prevFullLog = $this->fuelLogRepo->findLatestFullLogBefore($myBike, $currentOdometer);

            if ($prevFullLog) {
                // DBの値を数値化 (ここがエラーの原因でした)
                $prevOdometer = (float) $prevFullLog->odometer;

                // 2. その間の「ちょい足し給油」の量を合計
                $additions = $this->fuelLogRepo->sumQuantityBetween(
                    $myBike,
                    $prevOdometer,
                    $currentOdometer
                );

                // 3. 総消費量
                $currentQuantity = (float) $data['quantity'];
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

        if (! empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float) $data['odometer']);
        }
    }

    /**
     * 車種を検索する
     */
    public function searchModels(string $keyword): array
    {
        $models = $this->bikeModelRepo->searchByName($keyword, 20);

        return $models->map(fn ($m) => [
            'id' => $m->id,
            'text' => "{$m->manufacturer->name} {$m->name}",
            'image' => $m->image_url,
        ])->toArray();
    }
}
