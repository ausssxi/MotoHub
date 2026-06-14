<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 愛車ギャラリー画像（private）
    |--------------------------------------------------------------------------
    | ユーザーがアップロードした愛車写真は「非公開ディスク」に保存し、
    | owner-check付きの配信ルート経由でのみ返す（URL直叩きでも非所有者は403/404）。
    | 既定の 'local'（storage/app）は公開シンボリックリンクの外＝直接URL到達不可。
    */
    'image_disk' => env('GARAGE_IMAGE_DISK', 'local'),

    // 1台あたりのギャラリー画像上限
    'max_images' => (int) env('GARAGE_MAX_IMAGES', 20),

    // アップロード許容サイズ（KB）。スマホ写真を見込んで広め。
    'max_upload_kb' => (int) env('GARAGE_MAX_UPLOAD_KB', 8192),

    // 保存時の最適化（EXIF回転補正 + 長辺リサイズ + 再エンコード）
    'resize_max_edge' => 1600,
    'jpeg_quality' => 82,
];
