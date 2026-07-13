<?php

declare(strict_types=1);

namespace App\Http\Requests\Bike;

use App\Models\DiscussionThread;
use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 統合スレッドの新規作成。ログイン不要（安全弁完備が前提）。
 */
final class StoreDiscussionThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(DiscussionThread::TYPES)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'nickname' => ['nullable', 'string', 'max:50'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }

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
