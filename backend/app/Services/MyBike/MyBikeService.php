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
     * 車種ページ「この車種のオーナーのガレージ」用カード。is_public のガレージのみ・本名は出さない（ハンドル）。
     * 各カードに走行距離/累計維持費/平均燃費（garageShareStats＝シェアと同一ソース）を添える。
     * model_detail のキャッシュ blob に焼くため軽量配列で返す。
     *
     * @return array{cards: \Illuminate\Support\Collection<int, array<string, mixed>>, total: int}
     */
    public function publicGarageCardsForModel(int $bikeModelId, int $limit = 6): array
    {
        $base = MyBike::where('bike_model_id', $bikeModelId)->where('is_public', true);

        $cards = (clone $base)
            ->with(['user', 'images', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs', 'customRecords'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (MyBike $bike) {
                $s = $this->garageShareStats($bike);

                return [
                    'id' => $bike->id,
                    'image' => $bike->display_image,
                    'bike_name' => $bike->display_name,
                    'model_year' => $bike->model_year,
                    'handle' => $s['handle'],
                    'odometer' => $s['odometer'],
                    'total_cost' => $s['total_cost'],
                    'avg_efficiency' => $s['avg_efficiency'],
                ];
            });

        return ['cards' => $cards, 'total' => (clone $base)->count()];
    }

    /**
     * 外部シェア（OGP画像／シェア文言）用の集計（公開ガレージの集計のみ・本名や個別記録の詳細は含めない）。
     * 表示名は公開ハンドル（review_display_name）。本名（user->name）は絶対に使わない。
     *
     * @return array{handle: string, bike_name: string, manufacturer: string, odometer: int, total_cost: int, last12_cost: int, avg_efficiency: ?float}
     */
    public function garageShareStats(MyBike $myBike): array
    {
        $d = $this->buildDashboard($myBike);

        return [
            'handle' => $myBike->user->review_display_name ?? '名無しライダー',
            'bike_name' => $myBike->display_name,
            'manufacturer' => $myBike->bikeModel->manufacturer->name ?? '',
            'odometer' => (int) $myBike->current_odometer,
            'total_cost' => (int) $d['cost']['total'],
            'last12_cost' => (int) $d['cost']['last12'],
            'avg_efficiency' => $d['fuelChart']['average'],
        ];
    }

    /**
     * 会計簿（維持費の数値レポート）。オンザフライ集計（個人データ・少量・ログイン必須）。
     * 総維持費＝整備＋カスタム＋燃料／費目別内訳／km単価（累計総費用÷累計走行距離）／月別・年別・累計。
     * 記録の追加/削除で次表示時に再計算される（current_odometer も recompute 済＝整合）。
     *
     * @return array<string, mixed>
     */
    public function buildLedger(MyBike $myBike): array
    {
        $maint = $myBike->maintenanceLogs;   // type=maintenance
        $custom = $myBike->customRecords;    // type=custom
        $fuel = $myBike->fuelLogs;

        $maintTotal = (int) $maint->sum('cost');
        $customTotal = (int) $custom->sum('cost');
        $fuelTotal = (int) $fuel->sum('cost');
        $total = $maintTotal + $customTotal + $fuelTotal;

        // --- 費目別内訳（整備は title から費目へ分類＋カスタム＋燃料）---
        $byCategory = [];
        foreach ($maint as $m) {
            if (! $m->cost) {
                continue;
            }
            $cat = $this->maintenanceCostCategory((string) $m->title);
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (int) $m->cost;
        }
        if ($customTotal > 0) {
            $byCategory['カスタム'] = $customTotal;
        }
        if ($fuelTotal > 0) {
            $byCategory['燃料'] = $fuelTotal;
        }
        arsort($byCategory);
        $categories = [];
        foreach ($byCategory as $label => $amount) {
            $categories[] = [
                'label' => $label,
                'amount' => $amount,
                'percent' => $total > 0 ? (int) round($amount / $total * 100) : 0,
            ];
        }

        // --- km単価（累計）＝総費用 ÷ 走行距離(current − initial)。ゼロ除算は null ---
        $distance = max(0.0, (float) $myBike->current_odometer - (float) $myBike->initial_odometer);
        $perKm = $distance > 0 ? round($total / $distance, 1) : null;

        // --- 期間別（年別・月別=直近12ヶ月）---
        $byYear = $this->ledgerByPeriod($maint, $custom, $fuel, 'Y');
        $byMonth = $this->ledgerByPeriod($maint, $custom, $fuel, 'Y-m', 12);

        // --- 対象期間 ---
        $dates = $maint->pluck('maintained_at')
            ->merge($custom->pluck('maintained_at'))
            ->merge($fuel->pluck('filled_at'))
            ->filter()
            ->sort()
            ->values();

        return [
            'total' => $total,
            'maintenance_total' => $maintTotal,
            'custom_total' => $customTotal,
            'fuel_total' => $fuelTotal,
            'categories' => $categories,
            'distance' => $distance,
            'per_km' => $perKm,
            'by_year' => $byYear,
            'by_month' => $byMonth,
            'from' => $dates->first(),
            'to' => $dates->last(),
            'record_count' => $maint->count() + $custom->count() + $fuel->count(),
        ];
    }

    /**
     * Drivvo型「タイプ別 詳細レポート」。buildLedger / buildDashboard の出力を集約・深掘りする
     * （重い再集計はせず、既に計算済みの集計値と eager-load 済みコレクションを使う）。
     * 編集後も buildLedger 同様オンザフライで自動整合。
     *
     * @param  array<string, mixed>  $ledger  buildLedger() の戻り値
     * @param  array<string, mixed>  $dashboard  buildDashboard() の戻り値（fuelChart / reminders 再利用）
     * @return array<string, mixed>
     */
    public function buildLedgerReport(MyBike $myBike, array $ledger, array $dashboard): array
    {
        $maint = $myBike->maintenanceLogs;
        $custom = $myBike->customRecords;
        $fuel = $myBike->fuelLogs;

        // --- 総括（所有期間・月平均維持費・km単価）---
        $start = $myBike->purchased_at ?? $ledger['from']; // 購入日優先・無ければ最初の記録
        $monthsOwned = $start ? max(1, (int) $start->diffInMonths(now()) + 1) : null;
        $summary = [
            'from' => $start,
            'months_owned' => $monthsOwned,
            'total' => $ledger['total'],
            'monthly_avg' => $monthsOwned ? (int) round($ledger['total'] / $monthsOwned) : null,
            'per_km' => $ledger['per_km'],
            'distance' => $ledger['distance'],
        ];

        // --- 燃料レポート（既存 fuelChart の平均燃費を再利用＝無回帰）---
        $fuelCount = $fuel->count();
        $fuelWithCost = $fuel->filter(fn ($f) => $f->cost !== null && $f->cost > 0);
        $fuelDates = $fuel->pluck('filled_at')->filter()->sort()->values();
        $fuelOdos = $fuel->pluck('odometer')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        $fuelReport = [
            'count' => $fuelCount,
            'total' => $ledger['fuel_total'],
            'avg_quantity' => $fuelCount > 0 ? round((float) $fuel->sum('quantity') / $fuelCount, 2) : null,
            'avg_cost' => $fuelWithCost->count() > 0 ? (int) round((float) $fuelWithCost->sum('cost') / $fuelWithCost->count()) : null,
            'avg_efficiency' => $dashboard['fuelChart']['average'] ?? null,
            'last_at' => $fuelDates->last(),
            'days_per_fill' => $fuelDates->count() >= 2 ? round($fuelDates->first()->diffInDays($fuelDates->last()) / ($fuelDates->count() - 1), 1) : null,
            'km_per_fill' => $fuelOdos->count() >= 2 ? round(($fuelOdos->max() - $fuelOdos->min()) / ($fuelOdos->count() - 1)) : null,
        ];

        // --- 整備レポート（費目別＝3aと同じ分類で合計/回数/最終記録）＋ 次回予定（既存 reminders 再利用）---
        $maintCats = [];
        foreach ($maint as $m) {
            $cat = $this->maintenanceCostCategory((string) $m->title);
            $maintCats[$cat] ??= ['label' => $cat, 'total' => 0, 'count' => 0, 'last_at' => null];
            $maintCats[$cat]['total'] += (int) $m->cost;
            $maintCats[$cat]['count']++;
            if ($m->maintained_at && (! $maintCats[$cat]['last_at'] || $m->maintained_at->gt($maintCats[$cat]['last_at']))) {
                $maintCats[$cat]['last_at'] = $m->maintained_at;
            }
        }
        uasort($maintCats, fn ($a, $b) => $b['total'] <=> $a['total']);
        $maintReport = [
            'count' => $maint->count(),
            'total' => $ledger['maintenance_total'],
            'categories' => array_values($maintCats),
            'reminders' => $dashboard['reminders'], // 次回予定＝既存リマインダーをそのまま統合（再計算しない）
        ];

        // --- カスタムレポート（装着中スペック＝2aの installed_parts 再利用）---
        $customReport = [
            'count' => $custom->count(),
            'total' => $ledger['custom_total'],
            'installed' => $dashboard['installed_parts'],
            'installed_count' => $dashboard['installed_parts']->count(),
        ];

        return [
            'summary' => $summary,
            'fuel' => $fuelReport,
            'maintenance' => $maintReport,
            'custom' => $customReport,
        ];
    }

    /**
     * 会計簿のグラフ入力に整形する（純粋変換・再集計しない）。
     * 月次/年別は時系列「昇順」（by_month/by_year は新しい順なので反転）、費目別は 3a の降順を踏襲。
     * 空データは labels/values 空配列（描画側でフォールバック）。
     *
     * @param  array<string, mixed>  $ledger  buildLedger() の戻り値
     * @return array{monthly: array{labels: array<int,string>, values: array<int,int>}, yearly: array{labels: array<int,string>, values: array<int,int>}, category: array{labels: array<int,string>, values: array<int,int>}}
     */
    public function ledgerChartData(array $ledger): array
    {
        $months = array_reverse($ledger['by_month'] ?? [], true); // 古い順へ
        $monthly = [
            'labels' => array_keys($months),
            'values' => array_map(fn ($r) => (int) $r['total'], array_values($months)),
        ];

        $years = array_reverse($ledger['by_year'] ?? [], true);   // 古い順へ
        $yearly = [
            'labels' => array_map(fn ($y) => $y.'年', array_keys($years)),
            'values' => array_map(fn ($r) => (int) $r['total'], array_values($years)),
        ];

        $category = [
            'labels' => array_map(fn ($c) => (string) $c['label'], $ledger['categories'] ?? []),
            'values' => array_map(fn ($c) => (int) $c['amount'], $ledger['categories'] ?? []),
        ];

        return ['monthly' => $monthly, 'yearly' => $yearly, 'category' => $category];
    }

    /**
     * 期間別の費用集計（maintenance/custom/fuel/total）。$fmt='Y'|'Y-m'。$limit 指定で直近Nのみ。
     *
     * @return array<string, array<string, int>>
     */
    private function ledgerByPeriod($maint, $custom, $fuel, string $fmt, ?int $limit = null): array
    {
        $rows = [];
        $add = function ($date, string $bucket, int $cost) use (&$rows, $fmt) {
            if (! $date) {
                return;
            }
            $k = $date->format($fmt);
            $rows[$k][$bucket] = ($rows[$k][$bucket] ?? 0) + $cost;
        };

        foreach ($maint as $m) {
            $add($m->maintained_at, 'maintenance', (int) $m->cost);
        }
        foreach ($custom as $c) {
            $add($c->maintained_at, 'custom', (int) $c->cost);
        }
        foreach ($fuel as $f) {
            $add($f->filled_at, 'fuel', (int) $f->cost);
        }

        krsort($rows);
        foreach ($rows as $k => &$r) {
            $r['maintenance'] = $r['maintenance'] ?? 0;
            $r['custom'] = $r['custom'] ?? 0;
            $r['fuel'] = $r['fuel'] ?? 0;
            $r['total'] = $r['maintenance'] + $r['custom'] + $r['fuel'];
        }
        unset($r);

        return $limit !== null ? array_slice($rows, 0, $limit, true) : $rows;
    }

    /**
     * 整備 title を会計簿の費目へ分類する（単一の真実）。custom→カスタム/fuel→燃料は呼び出し側。
     */
    private function maintenanceCostCategory(string $title): string
    {
        if (str_contains($title, 'オイル')) {
            return 'オイル';
        }
        if (str_contains($title, 'タイヤ')) {
            return 'タイヤ';
        }
        if (str_contains($title, 'ブレーキ')) {
            return 'ブレーキ';
        }
        if (str_contains($title, 'チェーン') || str_contains($title, 'スプロケ')) {
            return 'チェーン';
        }
        if (str_contains($title, '車検')) {
            return '車検';
        }

        return 'その他整備';
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
        // エンジンオイルのみ 3000km。フォークオイル等は別物なので除外（誤発火防止＝安全側で interval 無し）。
        if (str_contains($title, 'オイル') && ! str_contains($title, 'フォーク')) {
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
     * 愛車情報（愛称＋走行距離=初期値）を編集する。車種・公開設定は変更しない。
     * km単価は buildLedger がオンザフライで再計算（current − initial）＝次回表示で反映。
     * current_odometer(running-max) は initial を含む max なので recompute で整合させる
     *（新 initial が記録最大より大きければ current も引き上げられる）。
     */
    public function updateBike(MyBike $myBike, array $data): void
    {
        DB::transaction(function () use ($myBike, $data) {
            // name カラムは NOT NULL かつ「ニックネームまたは車種名」を保持する設計。
            // 愛称が空なら車種名にフォールバック（登録時と同じ＝display_name は車種名表示になる）。
            $name = trim((string) ($data['name'] ?? ''));
            $myBike->name = $name !== '' ? $name : ($myBike->bikeModel->name ?? $myBike->name);
            $myBike->initial_odometer = $data['initial_odometer'] ?? 0;
            // recomputeCurrentOdometer が save() するため dirty な name/initial も併せて永続化される。
            $this->recomputeCurrentOdometer($myBike);
        });
    }

    /**
     * 走行距離(初期値)の妥当性警告。既存記録のいずれかが新 initial より小さい＝時系列矛盾のとき確認文を返す。
     * ★hard block しない（問題1の距離ガードと同じ流儀・保存は可）。問題なければ null。
     */
    public function initialOdometerWarning(MyBike $myBike, ?float $newInitial): ?string
    {
        if ($newInitial === null) {
            return null;
        }

        $minFuel = $myBike->fuelLogs()->min('odometer');
        $minRecord = MaintenanceLog::where('my_bike_id', $myBike->id)->whereNotNull('odometer')->min('odometer');
        $mins = array_filter([$minFuel, $minRecord], fn ($v) => $v !== null);
        if ($mins === []) {
            return null; // 記録なし＝矛盾しようがない
        }

        $minRecorded = (float) min($mins);
        if ($newInitial > $minRecorded) {
            return '入力した走行距離 '.$this->formatKmValue($newInitial).' km より小さい記録（最小 '.$this->formatKmValue($minRecorded).' km）があります。確認してください。';
        }

        return null;
    }

    private function formatKmValue(float $v): string
    {
        return $v == floor($v) ? (string) (int) $v : (string) $v;
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
     * 整備を記録し、作成した記録を返す（odometer の running-max 更新込み）。
     * odometer 前回比ガードは odometerWarningFor() で別途取得（保存は止めない）。
     */
    public function recordMaintenance(MyBike $myBike, array $data): MaintenanceLog
    {
        $data['type'] = MaintenanceLog::TYPE_MAINTENANCE;
        $record = $this->maintenanceLogRepo->create($myBike, $data);

        if (! empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float) $data['odometer']);
        }

        return $record;
    }

    /**
     * カスタム（パーツ装着等）を記録し、作成した記録を返す。title は part_name を流用（既存NOT NULLと一覧の互換）。
     */
    public function recordCustom(MyBike $myBike, array $data): MaintenanceLog
    {
        $data['type'] = MaintenanceLog::TYPE_CUSTOM;
        $data['title'] = $data['part_name'] ?? null;
        $record = $myBike->customRecords()->create($data);

        if (! empty($data['odometer'])) {
            $this->myBikeRepo->updateOdometerIfGreater($myBike, (float) $data['odometer']);
        }

        return $record;
    }

    /**
     * odometer ガードの警告文（時系列文脈・保存前に呼ぶこと）。
     * 入力日（maintained_at / filled_at）の文脈で逆行のみ警告する。日付が無ければ running-max にフォールバック。
     * 編集時は自分自身を $excludeFuelId / $excludeRecordId で除外する。
     */
    public function odometerWarningFor(MyBike $myBike, array $data, ?int $excludeFuelId = null, ?int $excludeRecordId = null): ?string
    {
        $onDate = $data['maintained_at'] ?? $data['filled_at'] ?? null;

        return $myBike->odometerPlausibilityWarning($this->odometerValue($data), $onDate, $excludeFuelId, $excludeRecordId);
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

    /**
     * 給油記録を編集し、派生状態（efficiency／current_odometer）を再計算する（削除時 recompute を流用）。
     * owner所有の bike に属する行のみ（findOrFail＝非所有は404）。
     */
    public function updateFuelLog(MyBike $myBike, int $fuelLogId, array $data): void
    {
        DB::transaction(function () use ($myBike, $fuelLogId, $data) {
            $log = $myBike->fuelLogs()->findOrFail($fuelLogId);
            $log->update([
                'filled_at' => $data['filled_at'],
                'odometer' => $data['odometer'],
                'quantity' => $data['quantity'],
                'cost' => $data['cost'] ?? null,
                'is_full_tank' => ! empty($data['is_full_tank']) && $data['is_full_tank'],
                'memo' => $data['memo'] ?? null,
            ]);
            // 走行距離/満タン/量の変更は前後の燃費に波及するため全件再計算（削除時と同一）。
            $this->recomputeFuelEfficiency($myBike);
            $this->recomputeCurrentOdometer($myBike);
        });
    }

    /**
     * 整備記録を編集する（type=maintenance の行のみ＝type検証込み・findOrFail で非所有/型違いは404）。
     * 維持費/リマインダーはオンザフライ集計のため次回表示で自動反映。current_odometer のみ再計算。
     */
    public function updateMaintenance(MyBike $myBike, int $recordId, array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($myBike, $recordId, $data) {
            $record = MaintenanceLog::where('my_bike_id', $myBike->id)->maintenance()->findOrFail($recordId);
            $record->update([
                'maintained_at' => $data['maintained_at'],
                'title' => $data['title'],
                'odometer' => $data['odometer'] ?? null,
                'cost' => $data['cost'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $this->recomputeCurrentOdometer($myBike);

            return $record;
        });
    }

    /**
     * カスタム記録を編集する（type=custom の行のみ＝type検証込み）。title は part_name を流用（一覧の互換）。
     */
    public function updateCustom(MyBike $myBike, int $recordId, array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($myBike, $recordId, $data) {
            $record = MaintenanceLog::where('my_bike_id', $myBike->id)->custom()->findOrFail($recordId);
            $record->update([
                'maintained_at' => $data['maintained_at'],
                'part_name' => $data['part_name'],
                'title' => $data['part_name'] ?? null,
                'brand' => $data['brand'] ?? null,
                'category' => $data['category'] ?? null,
                'odometer' => $data['odometer'] ?? null,
                'cost' => $data['cost'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'note' => $data['note'] ?? null,
                'is_installed' => ! empty($data['is_installed']) && $data['is_installed'],
            ]);
            $this->recomputeCurrentOdometer($myBike);

            return $record;
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
