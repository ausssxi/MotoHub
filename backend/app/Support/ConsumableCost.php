<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\ModelFitment;

/**
 * 消耗品（現状 battery / plug）の年額を計算する層。表示は持たない（画面反映は次タスク）。
 *
 * 設計:
 *  - 数値パラメータ（寿命年数・交換km・ロングライフ判定）は config/consumables.php に集約。
 *    Blade に文字列で散っていた「2〜3年」「3,000〜5,000km」を計算可能な数値へ橋渡しする。
 *  - cost_part_min（実勢価格の第1四分位）が無い行は available=false・reason 付きにし、
 *    0円として合計に混ぜない（純正専用品＝マーケット非流通のとき維持費が過小になるのを防ぐ）。
 *    実例: ハヤブサのバッテリー HY110SS（エリーパワー製リチウムイオンの純正専用品）は流通せず価格が取れない。
 *  - oil / tire は本タスク対象外。返り値の 'uncounted' で未算入を明示する。
 */
final class ConsumableCost
{
    /**
     * @return array{
     *   annual_km:int,
     *   items:array<int,array{key:string,label:string,part_no:?string,unit_price:?int,cycle:?string,annual_cost:?int,available:bool,reason:?string,plugs:?int}>,
     *   uncounted:array<int,string>
     * }
     */
    public static function forModel(BikeModel $model, ?int $annualKm = null): array
    {
        $annualKm = $annualKm ?? (int) config('consumables.default_annual_km');

        return [
            'annual_km' => $annualKm,
            'items' => [
                self::battery($model),
                self::plug($model, $annualKm),
            ],
            // oil / tire は数値の部品代が未整備のため本タスクでは算入しない。未算入として明示する。
            'uncounted' => ['エンジンオイル', 'タイヤ'],
        ];
    }

    /**
     * バッテリー年額 = cost_part_min ÷ 寿命年数。走行距離には依存しない。
     *
     * @return array<string, mixed>
     */
    private static function battery(BikeModel $model): array
    {
        $item = self::baseItem('battery', 'バッテリー');

        $row = self::representativeRow($model, 'battery');
        if ($row === null) {
            $item['reason'] = 'verified な適合データなし';

            return $item;
        }

        $lifeYears = max(1, (int) config('consumables.battery.life_years'));
        $item['part_no'] = self::partNo($row);
        $item['cycle'] = $lifeYears.'年';

        $price = self::costMin($row);
        if ($price === null) {
            $item['reason'] = '価格データなし（純正専用品の可能性）';

            return $item;
        }

        $item['unit_price'] = $price;
        $item['annual_cost'] = (int) round($price / $lifeYears);
        $item['available'] = true;

        return $item;
    }

    /**
     * プラグ年額 = cost_part_min × spec.plugs ÷ interval_km × annualKm。
     * spec.plugs が無ければ 1 本として扱い reason に残す。品番マーカーでロングライフ判定。
     *
     * @return array<string, mixed>
     */
    private static function plug(BikeModel $model, int $annualKm): array
    {
        $item = self::baseItem('plug', 'プラグ');

        $row = self::representativeRow($model, 'plug');
        if ($row === null) {
            $item['reason'] = 'verified な適合データなし';

            return $item;
        }

        $partNo = self::partNo($row);

        // 必要本数。spec.plugs が無ければ 1 本と仮定し、その旨を注記する。
        $notes = [];
        $plugsRaw = data_get($row, 'spec.plugs');
        if ($plugsRaw === null || $plugsRaw === '' || (int) $plugsRaw < 1) {
            $plugs = 1;
            $notes[] = 'spec.plugs 不明のため1本と仮定';
        } else {
            $plugs = (int) $plugsRaw;
        }

        // 品番のマーカーでロングライフ（イリジウム/白金系）判定 → 交換サイクルを切り替える。
        $isLongLife = self::isLongLifePlug($partNo);
        $interval = max(1, (int) config('consumables.plug.interval_km.'.($isLongLife ? 'long_life' : 'standard')));
        if ($isLongLife) {
            $notes[] = 'ロングライフ品番と判定';
        }

        $item['part_no'] = $partNo;
        $item['plugs'] = $plugs;
        $item['cycle'] = number_format($interval).'km';

        $price = self::costMin($row);
        if ($price === null) {
            $notes[] = '価格データなし（純正専用品の可能性）';
            $item['reason'] = implode(' / ', $notes);

            return $item;
        }

        $item['unit_price'] = $price;
        $item['annual_cost'] = (int) round($price * $plugs / $interval * $annualKm);
        $item['available'] = true;
        // 算定できていても、本数仮定・ロングライフ等の注記は残す（人が妥当性を判断できるように）。
        $item['reason'] = $notes !== [] ? implode(' / ', $notes) : null;

        return $item;
    }

    /**
     * task の verified 行から代表1行を選ぶ。cost_part_min を持つ行を優先し、無ければ先頭行
     * （part_no は出しつつ available=false にできる）。frame_code 順で決定的。
     */
    private static function representativeRow(BikeModel $model, string $task): ?ModelFitment
    {
        $rows = ModelFitment::query()
            ->where('bike_model_id', $model->id)
            ->where('task', $task)
            ->verified()
            ->orderBy('frame_code')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->first(fn (ModelFitment $r): bool => self::costMin($r) !== null) ?? $rows->first();
    }

    private static function costMin(ModelFitment $row): ?int
    {
        $v = $row->cost_part_min;

        return ($v === null || $v === '') ? null : (int) $v;
    }

    private static function partNo(ModelFitment $row): ?string
    {
        $p = trim((string) $row->recommended_part_no);

        return $p !== '' ? $p : null;
    }

    /** 品番がロングライフ（イリジウム/白金系）マーカーを含むか（config のマーカー・大文字部分一致）。 */
    private static function isLongLifePlug(?string $partNo): bool
    {
        if ($partNo === null || $partNo === '') {
            return false;
        }

        $upper = strtoupper($partNo);
        foreach ((array) config('consumables.plug.long_life_markers', []) as $marker) {
            $marker = strtoupper((string) $marker);
            if ($marker !== '' && str_contains($upper, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 各消耗品アイテムの初期形（必須キー＋補助キー plugs）。
     *
     * @return array<string, mixed>
     */
    private static function baseItem(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'part_no' => null,
            'unit_price' => null,
            'cycle' => null,
            'annual_cost' => null,
            'available' => false,
            'reason' => null,
            'plugs' => null, // プラグの本数（battery では null）。計算過程の表示に使う補助キー。
        ];
    }
}
