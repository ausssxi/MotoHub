<?php

declare(strict_types=1);

namespace App\Http\Requests\RentalGarage;

use App\Support\JapanCityPrefecture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRentalGarageRequest extends FormRequest
{
    /** ハニーポットに入力があった（＝ボット）ときに true。controller が破棄判定に使う。 */
    public bool $honeypotTriggered = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 防御2 ハニーポット: 非表示フィールド company_website に入力があればボットとみなす。
     * その場合はルールを空にして「エラー0件で通過」させ、controller 側で成功を装って破棄する
     * （バリデーションエラーを一切出さないことでボットに検知させない）。
     */
    protected function prepareForValidation(): void
    {
        if (filled($this->input('company_website'))) {
            $this->honeypotTriggered = true;
        }
    }

    public function rules(): array
    {
        if ($this->honeypotTriggered) {
            return []; // ボットにはルールを課さない（後段で破棄）
        }

        return [
            // 必須
            'name' => ['required', 'string', 'max:150'],
            'garage_type' => ['required', 'in:indoor,container,open,other'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'prefecture' => ['required', 'string', 'max:10', Rule::in(JapanCityPrefecture::PREFECTURES)],
            // 任意（各カラムの桁数・型に厳密対応）
            'operator' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:8'],
            'city' => ['nullable', 'string', 'max:50'],
            'monthly_fee_min' => ['nullable', 'integer', 'min:0', 'max:4294967295'], // unsignedInteger
            'monthly_fee_max' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'size_text' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'], // unsignedSmallInteger
            'phone' => ['nullable', 'string', 'max:20'],
            'website_url' => ['nullable', 'string', 'max:255', 'url:http,https'], // http/https のみ
            'description' => ['nullable', 'string', 'max:1000'],
            // 設備は「あり(1)/なし(0)/不明(未指定)」の3択。未指定は null（false と区別）。
            'is_24h' => ['nullable', 'in:0,1'],
            'has_power' => ['nullable', 'in:0,1'],
            'has_security' => ['nullable', 'in:0,1'],
            'has_shutter' => ['nullable', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '施設名を入力してください。',
            'garage_type.required' => 'ガレージの種類を選択してください。',
            'garage_type.in' => 'ガレージの種類の選択が不正です。',
            'address.required' => '住所を入力してください。',
            'latitude.required' => '地図上で位置を指定してください。',
            'longitude.required' => '地図上で位置を指定してください。',
            'prefecture.required' => '都道府県を入力してください。',
            'prefecture.in' => '都道府県が正しくありません。',
            'website_url.url' => '公式サイトURLは http:// または https:// で始まる形式で入力してください。',
        ];
    }
}
