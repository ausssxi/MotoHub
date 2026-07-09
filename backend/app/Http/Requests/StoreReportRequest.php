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
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Report::REPORTABLE_TYPES))],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', Rule::in(array_keys(Report::REASONS))],
            // ハニーポット: 通常ユーザーは空。値が入っていればボット。
            'website' => ['nullable', 'size:0'],
        ];
    }
}
