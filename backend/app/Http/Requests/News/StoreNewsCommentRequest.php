<?php

declare(strict_types=1);

namespace App\Http\Requests\News;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * ニュースコメント投稿。ログイン不要（安全弁完備が前提）。
 */
final class StoreNewsCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:500'],
            'nickname' => ['nullable', 'string', 'max:50'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }

    /** NGワード（入口フィルタ）。ヒット語は開示せず中立な文言で弾く。批判は通す。 */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (app(NgWordFilter::class)->contains($this->input('body'))) {
                $v->errors()->add('body', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }
}
