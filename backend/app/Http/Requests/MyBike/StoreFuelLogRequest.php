<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

class StoreFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filled_at' => ['required', 'date'],
            'odometer' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0.1'],
            'cost' => ['nullable', 'integer', 'min:0'],
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
            'memo' => 'メモ',
        ];
    }
}