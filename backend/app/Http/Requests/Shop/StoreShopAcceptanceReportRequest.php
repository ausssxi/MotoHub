<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 店舗受け入れ情報の投稿バリデーション。
 * ポジティブなフラグ + 任意コメントのみ（評価・星は存在しない）。
 */
final class StoreShopAcceptanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accepts_other_store' => ['nullable', 'boolean'],
            'accepts_bring_in' => ['nullable', 'boolean'],
            'pickup_service' => ['nullable', 'boolean'],
            'walk_in_ok' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:120'],
            // 表示名。匿名の入力名、またはログイン初回のハンドル設定に使う（max 30）。
            'submitter_name' => ['nullable', 'string', 'max:30'],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }

    /** チェックボックス未送信を false に正規化。 */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'accepts_other_store' => $this->boolean('accepts_other_store'),
            'accepts_bring_in' => $this->boolean('accepts_bring_in'),
            'pickup_service' => $this->boolean('pickup_service'),
            'walk_in_ok' => $this->boolean('walk_in_ok'),
        ]);
    }

    /** 最低1つのフラグ、またはコメントが必要。 */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $anyFlag = $this->boolean('accepts_other_store')
                || $this->boolean('accepts_bring_in')
                || $this->boolean('pickup_service')
                || $this->boolean('walk_in_ok');
            if (! $anyFlag && trim((string) $this->input('comment')) === '') {
                $v->errors()->add('accepts_other_store', '当てはまる項目を1つ以上選ぶか、コメントを入力してください。');
            }

            // NGワード（入口フィルタ）。ヒット語は開示せず中立な文言で弾く。
            if (app(NgWordFilter::class)->contains($this->input('comment'))) {
                $v->errors()->add('comment', '不適切な表現が含まれている可能性があります。表現を見直してください。');
            }
        });
    }
}
