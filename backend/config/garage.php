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

    /*
    |--------------------------------------------------------------------------
    | 給油フォームの OCR 入力補完（撮影→抽出→ユーザー確認→保存）
    |--------------------------------------------------------------------------
    | レシート/メーターを撮影し Claude vision で {走行距離,給油量,金額,日付} を抽出。
    | ★抽出値は自動保存しない（フォームに充填してユーザーが確認・修正してから保存）。
    | 送信前に Intervention 再エンコードで EXIF/GPS を除去する。
    */
    'ocr_enabled' => (bool) env('GARAGE_OCR_ENABLED', true),

    // vision モデル（精度が弱ければ Sonnet 等へ env / ここで差し替え）
    'ocr_model' => env('GARAGE_OCR_MODEL', 'claude-haiku-4-5-20251001'),

    // 1ユーザーあたりの 1日上限（コスト無防備を防ぐ・throttle:ocr-extract で適用）
    'ocr_max_per_day' => (int) env('GARAGE_OCR_MAX_PER_DAY', 20),

    // OCR送信前のリサイズ長辺（送信容量削減・精度確保のバランス）
    'ocr_max_edge' => 1600,

    /*
    |--------------------------------------------------------------------------
    | 給油フォームの音声入力補完（喋る→文字化→パース→確認→保存）
    |--------------------------------------------------------------------------
    | engine A = ブラウザの Web Speech API で文字化 → Claude（ocr_model）でパース。
    | ★音声そのものは当社サーバへ送られない（ブラウザの音声認識＝例:Google が文字化）。
    | ★抽出値は自動保存しない（フォーム充填→ユーザー確認→保存）。
    | 和文数字が弱ければ voice_engine を 'cloud_stt'（B案）へ差し替えられるよう config 化。
    */
    'voice_enabled' => (bool) env('GARAGE_VOICE_ENABLED', true),

    // STTエンジン: 'web_speech'（A・既定）/ 将来 'cloud_stt'（B）。パースは ocr_model を流用。
    'voice_engine' => env('GARAGE_VOICE_ENGINE', 'web_speech'),

    // パース(Haiku)叩きの 1ユーザー1日上限（音声は短時間に複数回叩きうるので OCR より緩め）
    'voice_max_per_day' => (int) env('GARAGE_VOICE_MAX_PER_DAY', 40),
];
