<?php

declare(strict_types=1);

namespace App\Http\Requests\Bike;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 車種Q&A の質問投稿。ログイン不要（安全弁完備が前提）。
 */
final class StoreModelQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'nickname' => ['nullable', 'string', 'max:50'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
            // 「回答が付いたら通知」の購読情報（任意・JSがsubmit時に詰める。無ければ通知なしで正常完了）
            'push_endpoint' => ['nullable', 'string', 'max:1000'],
            'push_p256dh' => ['nullable', 'string', 'max:255'],
            'push_auth' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** NGワード（入口フィルタ）。ヒット語は開示せず中立な文言で弾く。批判は通す。 */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ng = app(NgWordFilter::class);
            if ($ng->contains($this->input('title')) || $ng->contains($this->input('body'))) {
                $v->errors()->add('title', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }
}
