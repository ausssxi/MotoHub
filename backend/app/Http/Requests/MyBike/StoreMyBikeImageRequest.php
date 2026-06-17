<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMyBikeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有者チェックはコントローラ（getBikeDetail＝非所有者404）で実施する。
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,heic,heif',
                'max:'.(int) config('garage.max_upload_kb'),
            ],
            'caption' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => '画像を選択してください。',
            'image.image' => '画像ファイル（JPEG / PNG / WebP）を選択してください。',
            'image.mimes' => '対応形式は JPEG / PNG / WebP です。',
            'image.max' => '画像サイズが大きすぎます（最大 '.number_format((int) config('garage.max_upload_kb') / 1024, 0).'MB）。',
            'caption.max' => 'キャプションは100文字以内で入力してください。',
        ];
    }
}
