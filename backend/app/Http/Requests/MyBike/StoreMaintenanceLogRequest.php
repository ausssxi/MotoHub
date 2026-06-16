<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有者チェックはコントローラ（getBikeDetail＝非所有者404）で実施＝全ガレージ系で404に統一。
        return true;
    }

    public function rules(): array
    {
        return [
            'maintained_at' => ['required', 'date', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:50'],
            'cost' => ['nullable', 'integer', 'min:0'],
            'odometer' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'maintained_at' => '整備日',
            'title' => '整備内容',
            'cost' => '費用',
            'odometer' => '走行距離',
            'note' => '詳細メモ',
        ];
    }
}
