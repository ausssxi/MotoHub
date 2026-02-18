<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreMyBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ログインしているユーザーのみ許可
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            // 車種はマスタから選択するため必須
            'bike_model_id' => ['required', 'exists:bike_models,id'],
            // 小数点(0.1km)も許容するため integer ではなく numeric
            'initial_odometer' => ['nullable', 'numeric', 'min:0'],
            'name' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bike_model_id' => '車種',
            'initial_odometer' => '現在の走行距離',
            'name' => '愛称',
        ];
    }
}