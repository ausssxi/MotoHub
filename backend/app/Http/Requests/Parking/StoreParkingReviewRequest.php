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
            'nickname' => ['required', 'string', 'max:50'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:1000'],
            'visited_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.required' => 'ニックネームを入力してください。',
            'rating.required' => '評価を選択してください。',
            'body.required' => 'レビュー内容を入力してください。',
        ];
    }
}
