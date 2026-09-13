<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Google AdSense
    |--------------------------------------------------------------------------
    |
    | enabled が false のとき、ローダースクリプトも <ins> も一切出力しない
    | （ローカル/テストで広告を出すと無効なトラフィック扱いのリスク）。
    | slots の各IDが空文字なら、その枠は描画しない（空の <ins> を置かない）。
    | ★ client / slot ID はコードに直書きせず .env から読む。
    |
    */
    'enabled' => env('ADSENSE_ENABLED', false),

    'client' => env('ADSENSE_CLIENT', 'ca-pub-3690883624273126'),

    'slots' => [
        'blog_mid' => env('ADSENSE_SLOT_BLOG_MID', ''),
        'blog_bottom' => env('ADSENSE_SLOT_BLOG_BOTTOM', ''),
        // 第2・第3段階用（今回は貼らない・こちらの判断で env にIDを入れて有効化）。
        'parking' => env('ADSENSE_SLOT_PARKING', ''),
        'bike_model' => env('ADSENSE_SLOT_BIKE_MODEL', ''),
    ],
];
