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
use Illuminate\Support\Facades\DB;

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
        $currentOdometer = (float) $data['odometer'];
        $isFull = ! empty($data['is_full_tank']) && $data['is_full_tank'];

        // 燃費は共通ヘルパで算出（挿入時＝再計算時で同一式を保証）。
        $data['efficiency'] = $this->computeEfficiency($myBike, $currentOdometer, (float) $data['quantity'], $isFull);

        $this->fuelLogRepo->create($myBike, $data);
        $this->myBikeRepo->updateOdometerIfGreater($myBike, $currentOdometer);
    }

    /**
     * 燃費（満タン基準）を算出する単一の真実の式。recordFuel と recomputeFuelEfficiency が共用。
     * 満タン行のみ、直近の満タン行を基準に「間のちょい足し量」を合算して距離/総量で算出。
     * 該当しなければ null（partial行・最初の満タン行・距離0等）。
     */
    private function computeEfficiency(MyBike $myBike, float $odometer, float $quantity, bool $isFull): ?float
    {
        if (! $isFull) {
            return null;
        }

        $prevFull = $this->fuelLogRepo->findLatestFullLogBefore($myBike, $odometer);
        if (! $prevFull) {
            return null;
        }

        $prevOdometer = (float) $prevFull->odometer;
        $additions = $this->fuelLogRepo->sumQuantityBetween($myBike, $prevOdometer, $odometer);
        $totalQuantity = $quantity + $additions;
        $distance = $odometer - $prevOdometer;

        return ($distance > 0 && $totalQuantity > 0) ? round($distance / $totalQuantity, 2) : null;
    }

    /**
     * 給油の派生状態を再計算（削除/編集後の整合）。
     * 全給油の efficiency を「既存と同一の式」で計算し直す（中間削除の再リンクを反映）。
     * 各行は odometer 照会で独立計算＝順序非依存。個人ログ規模なので全件再計算でよい。
     */
    public function recomputeFuelEfficiency(MyBike $myBike): void
    {
        foreach ($myBike->fuelLogs()->get() as $log) {
            $new = $this->computeEfficiency($myBike, (float) $log->odometer, (float) $log->quantity, (bool) $log->is_full_tank);
            $old = $log->efficiency === null ? null : (float) $log->efficiency;
            if ($old !== $new) {
                $log->efficiency = $new;
                $log->save();
            }
        }
    }

    /**
     * current_odometer(running-max) を再計算。
     * max( 残り fuel_logs.odometer ∪ maintenance_logs.odometer〔全type〕 ∪ 登録初期odometer )。
     * 登録初期を下回らない（max に含めることで担保）。
     */
    public function recomputeCurrentOdometer(MyBike $myBike): void
    {
        $maxFuel = (float) ($myBike->fuelLogs()->max('odometer') ?? 0);
        $maxRecord = (float) (MaintenanceLog::where('my_bike_id', $myBike->id)->max('odometer') ?? 0);
        $initial = (float) ($myBike->initial_odometer ?? 0);

        $myBike->current_odometer = max($maxFuel, $maxRecord, $initial);
        $myBike->save(); // MyBikeObserver が model_detail キャッシュを purge（既存パターン）
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
     * 給油記録を削除し、派生状態（efficiency／current_odometer）を再計算する。
     * トランザクション内で 行削除 → efficiency再計算 → current_odometer再計算。
     */
    public function deleteFuelLog(MyBike $myBike, int $fuelLogId): void
    {
        DB::transaction(function () use ($myBike, $fuelLogId) {
            $myBike->fuelLogs()->findOrFail($fuelLogId)->delete();
            $this->recomputeFuelEfficiency($myBike);
            $this->recomputeCurrentOdometer($myBike);
        });
    }

    /**
     * 記録（整備/カスタム）を削除（owner所有の bike に属する行のみ・type非依存）。
     * 最大odometerの記録削除で running-max が stale にならないよう current_odometer も再計算。
     */
    public function deleteRecord(MyBike $myBike, int $recordId): void
    {
        DB::transaction(function () use ($myBike, $recordId) {
            MaintenanceLog::where('my_bike_id', $myBike->id)->findOrFail($recordId)->delete();
            $this->recomputeCurrentOdometer($myBike);
        });
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
