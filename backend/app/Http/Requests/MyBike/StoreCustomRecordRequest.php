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
            // 商品連携（2a・任意）。クライアントが選んだ商品スナップショットを受ける。
            // 取得日時 product_price_fetched_at はサーバ側で付与（クライアントから受けない）。
            'product_mall' => ['nullable', 'string', 'in:rakuten,yahoo'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_image_url' => ['nullable', 'url', 'max:512'],
            'product_price' => ['nullable', 'integer', 'min:0'],
            'product_url' => ['nullable', 'url', 'max:1024'],
            // 添付写真（任意・複数）。HEIC は Imagick でデコードして受理（2b-3）。
            'images' => ['nullable', 'array', 'max:'.(int) config('garage.max_record_images', 10)],
            'images.*' => ['file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:'.(int) config('garage.max_upload_kb', 8192)],
        ];
    }
}
