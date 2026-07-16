<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | プロフィールアバター（public）
    |--------------------------------------------------------------------------
    | アバターは全公開面（プロフィール/ガレージ/レビュー/コメント）に表示するため、
    | 愛車写真（private local ディスク＋owner配信）とは異なり public ディスクへ保存する。
    | 画像処理（EXIF/GPS除去・HEIC対応）は MyBike の作法（Intervention v3 再エンコード）を流用。
    */
    'disk' => env('AVATAR_DISK', 'public'),

    // 保存ディレクトリ（public ディスク配下）。storage:link 経由で /storage/avatars/... に公開。
    'dir' => 'avatars',

    // アップロード許容サイズ（KB）。アバター1枚なので愛車写真より控えめ。
    'max_upload_kb' => (int) env('AVATAR_MAX_UPLOAD_KB', 5120),

    // 正方形クロップの一辺（px）。cover で中央クロップし、拡大はしない。
    'size' => (int) env('AVATAR_SIZE', 512),

    // 再エンコード品質（EXIF/GPS はこの toJpeg 再エンコードで完全除去される）。
    'jpeg_quality' => (int) env('AVATAR_JPEG_QUALITY', 82),
];
