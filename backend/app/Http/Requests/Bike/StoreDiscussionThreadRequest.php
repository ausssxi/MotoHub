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
            // 質問はFAQPage schema/SEOのため title 必須維持。casual(ひとこと)は本文だけで可。
            'title' => ['nullable', 'required_if:type,question', 'string', 'max:120'],
            // title 無しの casual でも「中身ゼロ」を防ぐため、title が無ければ本文必須。
            'body' => ['nullable', 'required_without:title', 'string', 'max:2000'],
            'nickname' => ['nullable', 'string', 'max:50'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
            // 「返信が付いたら通知」の購読情報（任意・JSがsubmit時に詰める。無ければ通知なしで正常完了）
            'push_endpoint' => ['nullable', 'string', 'max:1000'],
            'push_p256dh' => ['nullable', 'string', 'max:255'],
            'push_auth' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required_if' => '質問にはタイトルを入力してください。',
            'body.required_without' => '本文を入力してください。',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ng = app(NgWordFilter::class);
            // title は null 許容になったため (string) で null 安全化。フィールド別にエラーを載せる。
            $msg = '不適切な表現が含まれている可能性があります。表現を見直してください。';
            if ($ng->contains((string) $this->input('title'))) {
                $v->errors()->add('title', $msg);
            }
            if ($ng->contains((string) $this->input('body'))) {
                $v->errors()->add('body', $msg);
            }
        });
    }
}
