<?php

return [
    /*
    |--------------------------------------------------------------------------
    | listings 検索用denormカラムの最適化バッチ（bikes:optimize-search-data）
    |--------------------------------------------------------------------------
    | manufacturer_id/category_id/displacement を bike_model からコピーする処理を、
    | 深夜帯に分割実行して一括ジョブの高負荷を避けるためのパラメータ。
    */

    // 1回の実行で処理する最大件数（--limit 未指定時の既定）。
    'limit' => env('SEARCH_OPTIMIZE_LIMIT', 20000),

    // chunk 間の sleep（ミリ秒）。DB/検索を詰まらせない。0で無効。
    'sleep_ms' => env('SEARCH_OPTIMIZE_SLEEP_MS', 200),
];
