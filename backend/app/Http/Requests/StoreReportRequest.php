<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 不適切投稿の通報バリデーション。ログイン不要（口コミ投稿がログイン不要なため揃える）。
 * reportable は生のクラス名ではなく短トークン（type）で受け、allowlist で解決する。
 */
final class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // アバター通報は連番 user id を DOM に出さないため public_token で対象を渡す
        // （公開プロフィールが token をURLキーにしている原則＝ID列挙防止を通報でも守る）。
        // それ以外の対象は従来どおり数値 id。
        $isAvatar = $this->input('type') === 'user_avatar';

        return [
            'type' => ['required', 'string', Rule::in(array_keys(Report::REPORTABLE_TYPES))],
            'id' => [Rule::requiredIf(! $isAvatar), 'integer', 'min:1'],
            'token' => [Rule::requiredIf($isAvatar), 'string', 'alpha_num'],
            'reason' => ['nullable', 'string', Rule::in(array_keys(Report::REASONS))],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }
}
