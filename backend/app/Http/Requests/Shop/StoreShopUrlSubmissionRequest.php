<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 店詳細ページからの公式サイトURL提案（軽量）。URLのみ受け付ける。
 * honeypot は fax_number（URL入力欄と紛れないよう別名）。
 */
final class StoreShopUrlSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'website_url' => ['required', 'url', 'max:200'],
            'fax_number' => ['nullable', 'size:0'], // ハニーポット
        ];
    }

    public function messages(): array
    {
        return [
            'website_url.required' => '公式サイトのURLを入力してください。',
            'website_url.url' => 'URLはhttp(s)から始まる形式で入力してください。',
        ];
    }
}
