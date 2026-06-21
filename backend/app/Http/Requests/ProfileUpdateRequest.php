<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * 公開表示名はタグ除去＋トリム（既存のハンドル捕捉と同じ流儀）。空文字は null に正規化。
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('review_display_name')) {
            $handle = trim(strip_tags((string) $this->input('review_display_name')));
            $this->merge(['review_display_name' => $handle === '' ? null : $handle]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // 公開表示名（ハンドル）。任意・1〜30文字。一意化は当面非ゴール（重複許容）。
            'review_display_name' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'お名前',
            'review_display_name' => '公開表示名',
        ];
    }
}
