<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMyBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有者チェックはコントローラ（getBikeDetail＝非所有者404）で実施＝全ガレージ系で404に統一。
        return true;
    }

    public function rules(): array
    {
        return [
            // 愛称（任意・未入力可＝車種名にフォールバック）
            'name' => ['nullable', 'string', 'max:50'],
            // 走行距離（初期値）。小数点(0.1km)も許容するため numeric。
            'initial_odometer' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '愛称',
            'initial_odometer' => '走行距離',
        ];
    }
}
