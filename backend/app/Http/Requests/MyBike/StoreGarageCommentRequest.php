<?php

declare(strict_types=1);

namespace App\Http\Requests\MyBike;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 公開ガレージへの社交コメント（会員限定・ルートは auth middleware 配下）。
 */
final class StoreGarageCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // 会員限定
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:500'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'コメントを入力してください。',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ng = app(NgWordFilter::class);
            if ($ng->contains((string) $this->input('body'))) {
                $v->errors()->add('body', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }
}
