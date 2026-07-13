<?php

declare(strict_types=1);

namespace App\Http\Requests\Bike;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * スレッドへの返信。ログイン不要（安全弁完備が前提）。
 */
final class StoreDiscussionReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'size:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ng = app(NgWordFilter::class);
            if ($ng->contains($this->input('body'))) {
                $v->errors()->add('body', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }
}
