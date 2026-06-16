<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use App\Models\MaintenanceLog;
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
     * @return array{cost: array, fuelChart: array, reminders: array<int, array>, installed_parts: \Illuminate\Support\Collection}
     */
    public function buildDashboard(MyBike $myBike): array
    {
        $maint = $myBike->maintenanceLogs;   // type=maintenance（scoped）。リマインダーに使う
        $custom = $myBike->customRecords;    // type=custom。費用は維持費へ合算
        $fuel = $myBike->fuelLogs;
        $oneYearAgo = now()->subYear();

        // --- 1) 維持費（整備＋カスタム＋給油） ---
        $maintTotal = (int) $maint->sum('cost');
        $customTotal = (int) $custom->sum('cost');
        $fuelTotal = (int) $fuel->sum('cost');
        $last12 = (int) (
            $maint->filter(fn ($m) => $m->maintained_at && $m->maintained_at->gte($oneYearAgo))->sum('cost')
            + $custom->filter(fn ($c) => $c->maintained_at && $c->maintained_at->gte($oneYearAgo))->sum('cost')
            + $fuel->filter(fn ($f) => $f->filled_at && $f->filled_at->gte($oneYearAgo))->sum('cost')
        );

        $byYear = [];
        foreach ($maint as $m) {
            if ($m->maintained_at) {
                $y = $m->maintained_at->format('Y');
                $byYear[$y]['maintenance'] = ($byYear[$y]['maintenance'] ?? 0) + (int) $m->cost;
            }
        }
        foreach ($custom as $c) {
            if ($c->maintained_at) {
                $y = $c->maintained_at->format('Y');
                $byYear[$y]['custom'] = ($byYear[$y]['custom'] ?? 0) + (int) $c->cost;
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
            $row['custom'] = $row['custom'] ?? 0;
            $row['fuel'] = $row['fuel'] ?? 0;
            $row['total'] = $row['maintenance'] + $row['custom'] + $row['fuel'];
        }
        unset($row);

        $cost = [
            'total' => $maintTotal + $customTotal + $fuelTotal,
            'maintenance_total' => $maintTotal,
            'custom_total' => $customTotal,
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

        // --- 4) 今ついてる装備（custom・装着中）---
        $installedParts = $custom->where('is_installed', true)->values();

        return ['cost' => $cost, 'fuelChart' => $fuelChart, 'reminders' => $reminders, 'installed_parts' => $installedParts];
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
     * 整備を記録する。odometer の前回比ガード（既存ヘルパ再利用）を計算して返す（hard blockしない）。
     *
     * @return string|null odometer 警告（前回比10倍/逆行）or null
     */
    public function recordMaintenance(MyBike $myBike, array $data): ?string
    {
        // 警告は「前回(=現 current_odometer)」基準なので、ODO更新の前に算出する。
        $warning = $myBike->odometerPlausibilityWarning($this->odometerValue($data));

        $data['type'] = MaintenanceLog::TYPE_MAINTENANCE;
        $this->maintenanceLogRepo->create($myBike, $data);

        if (! empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float) $data['odometer']);
        }

        return $warning;
    }

    /**
     * カスタム（パーツ装着等）を記録する。title は part_name を流用（既存NOT NULLと一覧の互換）。
     *
     * @return string|null odometer 警告 or null
     */
    public function recordCustom(MyBike $myBike, array $data): ?string
    {
        $warning = $myBike->odometerPlausibilityWarning($this->odometerValue($data));

        $data['type'] = MaintenanceLog::TYPE_CUSTOM;
        $data['title'] = $data['part_name'] ?? null;
        $myBike->customRecords()->create($data);

        if (! empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float) $data['odometer']);
        }

        return $warning;
    }

    /**
     * 記録（整備/カスタム）を削除（owner所有の bike に属する行のみ・type非依存）。
     */
    public function deleteRecord(MyBike $myBike, int $recordId): void
    {
        MaintenanceLog::where('my_bike_id', $myBike->id)->findOrFail($recordId)->delete();
    }

    private function odometerValue(array $data): ?float
    {
        return isset($data['odometer']) && $data['odometer'] !== null && $data['odometer'] !== ''
            ? (float) $data['odometer']
            : null;
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
