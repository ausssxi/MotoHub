<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有者チェックはコントローラ（getBikeDetail＝非所有者404）で実施。
        return true;
    }

    public function rules(): array
    {
        return [
            'part_name' => ['required', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:'.implode(',', config('garage.custom_categories', []))],
            'maintained_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'integer', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_installed' => ['boolean'],
            // visibilityフック（今回サーフェス無し・既定 false）
            'is_public' => ['boolean'],
            // 添付写真（任意・複数）。HEIC は GD非対応のため mimes で弾く（2b-3でインフラ対応）。
            'images' => ['nullable', 'array', 'max:'.(int) config('garage.max_record_images', 10)],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.(int) config('garage.max_upload_kb', 8192)],
        ];
    }
}
