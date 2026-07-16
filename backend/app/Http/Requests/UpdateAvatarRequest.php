<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * アバターアップロードのバリデーション。
 * `mimes` は finfo による実体MIME判定（拡張子だけでなく中身を見る）＝偽装した実行可能ファイルや
 * 非画像を拒否する。サイズ上限も課す。HEIC は getimagesize 非対応のため `image` ルールは使わず
 * （愛車写真アップロードと同じ流儀）、非画像の最終防波堤は AvatarImageService の再エンコード例外に委ねる。
 * その再エンコードで EXIF/GPS も完全除去される。
 */
final class UpdateAvatarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,heic,heif', // finfo実体MIME判定＝偽装/非画像を拒否
                'max:'.(int) config('avatar.max_upload_kb'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'avatar' => 'プロフィールアイコン',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => '画像ファイル（JPEG / PNG / WebP / HEIC）を選択してください。',
            'avatar.mimes' => '対応形式は JPEG / PNG / WebP / HEIC です。',
            'avatar.max' => 'ファイルサイズが大きすぎます（上限 :max KB）。',
        ];
    }
}
