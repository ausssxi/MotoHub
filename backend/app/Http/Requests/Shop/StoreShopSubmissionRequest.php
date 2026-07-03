<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\ShopAcceptanceReport;
use App\Models\ShopSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ユーザー投稿の店舗登録バリデーション。
 * honeypot は fax_number（本フォームには実在の website_url があるため website 名は使わない）。
 */
final class StoreShopSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prefectures = collect(config('parking.regions', []))
            ->flatMap(fn ($prefs) => array_keys($prefs))
            ->all();

        return [
            'shop_name' => ['required', 'string', 'max:100'],
            'prefecture' => ['required', 'string', Rule::in($prefectures)],
            'city' => ['required', 'string', 'max:50'],
            'shop_type' => ['nullable', Rule::in(array_keys(ShopSubmission::SHOP_TYPE_OPTIONS))],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'website_url' => ['nullable', 'url', 'max:200'],
            'service_tags' => ['nullable', 'array'],
            'service_tags.*' => [Rule::in(ShopSubmission::SERVICE_TAG_OPTIONS)],
            'acceptance_flags' => ['nullable', 'array'],
            'acceptance_flags.*' => [Rule::in(array_keys(ShopAcceptanceReport::FLAGS))],
            'comment' => ['nullable', 'string', 'max:1000'],
            'submitter_name' => ['nullable', 'string', 'max:50'],
            // ハニーポット: 人間には非表示。値が入っていればボット。
            'fax_number' => ['nullable', 'size:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_name.required' => '店名を入力してください。',
            'prefecture.required' => '都道府県を選択してください。',
            'prefecture.in' => '都道府県を正しく選択してください。',
            'city.required' => '市区町村を入力してください。',
            'phone.regex' => '電話番号は数字とハイフンのみで入力してください。',
            'website_url.url' => 'URLはhttp(s)から始まる形式で入力してください。',
        ];
    }
}
