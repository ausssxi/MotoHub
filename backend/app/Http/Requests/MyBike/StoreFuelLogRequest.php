<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\MyBike;

class StoreFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $myBike = $this->route('myBike');
        
        if (!($myBike instanceof MyBike)) {
            $myBike = MyBike::find($myBike);
        }

        return $myBike && $myBike->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'filled_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer' => ['required', 'numeric', 'min:0'],
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
            
            if (!($myBike instanceof MyBike)) {
                $myBike = MyBike::find($myBike);
            }

            if (!$myBike) return;

            // ----------------------------------------------------
            // 購入時の走行距離（初期値）を下回っていないかチェック
            // ----------------------------------------------------
            // number_format に渡す前に (float) でキャストする
            if ((float)$data['odometer'] < (float)$myBike->initial_odometer) {
                $validator->errors()->add('odometer', "購入時の走行距離（" . number_format((float)$myBike->initial_odometer, 1) . "km）より少ない値は入力できません。");
            }

            // ----------------------------------------------------
            // 1. 走行距離の整合性チェック
            // ----------------------------------------------------
            
            // 入力日より「前」の最新記録を取得
            $prevLog = $myBike->fuelLogs()
                ->where('filled_at', '<=', $data['filled_at'])
                ->where('id', '!=', $this->route('fuel_log')?->id)
                ->orderBy('filled_at', 'desc')
                ->orderBy('odometer', 'desc')
                ->first();

            if ($prevLog && $data['odometer'] <= $prevLog->odometer) {
                $validator->errors()->add('odometer', "過去の記録（{$prevLog->filled_at->format('Y/m/d')}: {$prevLog->odometer}km）より値が増えていません。");
            }

            // 入力日より「後」の記録がある場合
            $nextLog = $myBike->fuelLogs()
                ->where('filled_at', '>', $data['filled_at'])
                ->orderBy('filled_at', 'asc')
                ->orderBy('odometer', 'asc')
                ->first();

            if ($nextLog && $data['odometer'] >= $nextLog->odometer) {
                $validator->errors()->add('odometer', "未来の記録（{$nextLog->filled_at->format('Y/m/d')}: {$nextLog->odometer}km）より値が大きくなっています。");
            }

            // ----------------------------------------------------
            // 2. ガソリン価格チェック
            // ----------------------------------------------------
            if (!empty($data['cost']) && !empty($data['quantity']) && $data['quantity'] > 0) {
                $unitPrice = $data['cost'] / $data['quantity'];
                if ($unitPrice < 100 || $unitPrice > 250) {
                    $validator->errors()->add('cost', "計算された単価（約".round($unitPrice)."円/L）が相場から大きく外れています。");
                }
            }
        });
    }
}