<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * お問い合わせフォームのバリデーションを担当
 */
final class ContactRequest extends FormRequest
{
    /**
     * 認証のチェック（今回は一般公開フォームなので常に true）
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'category' => ['required', 'string'],
            'message'  => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * エラーメッセージに表示する属性名の日本語化
     */
    public function attributes(): array
    {
        return [
            'name'     => 'お名前',
            'email'    => 'メールアドレス',
            'category' => 'お問い合わせ項目',
            'message'  => 'お問い合わせ内容',
        ];
    }
}