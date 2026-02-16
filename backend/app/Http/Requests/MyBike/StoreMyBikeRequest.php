<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

class StoreMyBikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'bike_model_id' => ['nullable', 'exists:bike_models,id'],
            'odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '愛車の名前',
            'bike_model_id' => '車種',
            'odometer' => '走行距離',
        ];
    }
}