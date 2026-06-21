<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use App\Models\MyBike;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有者チェックはコントローラ（getBikeDetail＝非所有者404）で実施＝全ガレージ系で404に統一。
        return true;
    }

    /**
     * 「距離が分からない」(odometer_unknown) が立っていたら odometer を null に正規化。
     * ＝ソフト必須：通常は必須、明示スキップ時のみ空許可。
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('odometer_unknown')) {
            $this->merge(['odometer' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'filled_at' => ['required', 'date', 'before_or_equal:today'],
            // ソフト必須：基本は入力。odometer_unknown=1 のときだけ空(null)許可（withValidator で必須判定）。
            'odometer' => ['nullable', 'numeric', 'min:0'],
            'odometer_unknown' => ['boolean'],
            'quantity' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'cost' => ['nullable', 'integer', 'min:0'],
            'is_full_tank' => ['boolean'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'filled_at' => '給油日',
            'odometer' => '総走行距離',
            'quantity' => '給油量',
            'cost' => '金額',
            'is_full_tank' => '満タン給油フラグ',
            'memo' => 'メモ',
        ];
    }

    public function messages(): array
    {
        return [
            'filled_at.required' => '給油日は必須です。',
            'filled_at.date' => '給油日は正しい日付形式で入力してください。',
            'filled_at.before_or_equal' => '給油日は今日までの日付を入力してください。',

            'odometer.required' => '総走行距離は必須です。',
            'odometer.numeric' => '総走行距離は数値で入力してください。',
            'odometer.min' => '総走行距離は0以上の数値を入力してください。',

            'quantity.required' => '給油量は必須です。',
            'quantity.numeric' => '給油量は数値で入力してください。',
            'quantity.min' => '給油量は0.1L以上で入力してください。',
            'quantity.max' => '給油量は100L以下で入力してください。',

            'cost.integer' => '金額は整数で入力してください。',
            'cost.min' => '金額は0円以上の数値を入力してください。',

            'memo.max' => 'メモは255文字以内で入力してください。',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();

            $myBike = $this->route('myBike');

            if (! ($myBike instanceof MyBike)) {
                $myBike = MyBike::find($myBike);
            }

            if (! $myBike) {
                return;
            }

            // 編集（fuel.update）時は対象行を整合性チェックから除外する。ルートパラメータ {fuelLog} の id。
            $excludeId = $this->route('fuelLog');
            $excludeId = $excludeId instanceof \App\Models\FuelLog ? $excludeId->id : ($excludeId !== null ? (int) $excludeId : null);

            // ガソリン価格チェック（距離の有無に関係なく実施）
            if (! empty($data['cost']) && ! empty($data['quantity']) && $data['quantity'] > 0) {
                $unitPrice = $data['cost'] / $data['quantity'];
                if ($unitPrice < 100 || $unitPrice > 250) {
                    $validator->errors()->add('cost', '計算された単価（約'.round($unitPrice).'円/L）が相場から大きく外れています。');
                }
            }

            // ----------------------------------------------------
            // ソフト必須：距離不明フラグが無いのに距離が空なら必須エラー
            // ----------------------------------------------------
            $odometer = $data['odometer'] ?? null;
            $unknown = $this->boolean('odometer_unknown');
            if (! $unknown && ($odometer === null || $odometer === '')) {
                $validator->errors()->add('odometer', '総走行距離は必須です。距離が分からない場合は「距離が分からない」を選んでください。');
            }

            // 以降の距離整合チェックは「距離あり」のときだけ（距離なし＝スキップ）
            if ($odometer === null || $odometer === '') {
                return;
            }
            $odometer = (float) $odometer;

            // 購入時の走行距離（初期値）を下回っていないか
            if ($odometer < (float) $myBike->initial_odometer) {
                $validator->errors()->add('odometer', '購入時の走行距離（'.number_format((float) $myBike->initial_odometer, 1).'km）より少ない値は入力できません。');
            }

            // 走行距離の整合性（前後の「距離あり」記録とのみ比較＝距離なし記録は基準にしない）
            $prevLog = $myBike->fuelLogs()
                ->whereNotNull('odometer')
                ->where('filled_at', '<=', $data['filled_at'])
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->orderBy('filled_at', 'desc')
                ->orderBy('odometer', 'desc')
                ->first();

            if ($prevLog && $odometer <= (float) $prevLog->odometer) {
                $validator->errors()->add('odometer', "過去の記録（{$prevLog->filled_at->format('Y/m/d')}: {$prevLog->odometer}km）より値が増えていません。");
            }

            $nextLog = $myBike->fuelLogs()
                ->whereNotNull('odometer')
                ->where('filled_at', '>', $data['filled_at'])
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->orderBy('filled_at', 'asc')
                ->orderBy('odometer', 'asc')
                ->first();

            if ($nextLog && $odometer >= (float) $nextLog->odometer) {
                $validator->errors()->add('odometer', "未来の記録（{$nextLog->filled_at->format('Y/m/d')}: {$nextLog->odometer}km）より値が大きくなっています。");
            }
        });
    }
}
