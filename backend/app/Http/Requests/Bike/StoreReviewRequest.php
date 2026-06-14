<?php

declare(strict_types=1);

namespace App\Http\Requests\Bike;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReviewRequest extends FormRequest
{
    /**
     * 権限チェック（今回は誰でも投稿可能なのでtrue）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:50'],
            // ログインユーザーの公開ハンドル（初回設定時のみ送信）。必須性は controller で auth状況に応じ判定。
            'review_handle' => ['nullable', 'string', 'max:30'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:1000'],
            'rating_design' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_engine' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_handling' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_fuel_economy' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_cost_performance' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * エラーメッセージのカスタマイズ（必要であれば）
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
            'title.required' => 'タイトルを入力してください。',
            'body.required' => 'レビュー内容を入力してください。',
        ];
    }
}
