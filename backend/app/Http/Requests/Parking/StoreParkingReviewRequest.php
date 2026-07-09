<?php

declare(strict_types=1);

namespace App\Http\Requests\Parking;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->nickname)) {
            $this->merge(['nickname' => '名無しライダー']);
        }
    }

    /** NGワード（入口フィルタ）。ヒット語は開示せず中立な文言で弾く。 */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (app(NgWordFilter::class)->contains($this->input('body'))) {
                $v->errors()->add('body', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
        ];
    }
}
