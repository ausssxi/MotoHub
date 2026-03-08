<?php

declare(strict_types=1);

namespace App\Http\Requests\Parking;

use Illuminate\Foundation\Http\FormRequest;

final class StoreParkingReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:50'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1000'],
            'visited_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->nickname)) {
            $this->merge(['nickname' => '名無しライダー']);
        }
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
        ];
    }
}
