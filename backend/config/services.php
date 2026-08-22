<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twitter' => [
        'consumer_key' => env('TWITTER_CONSUMER_KEY'),
        'consumer_secret' => env('TWITTER_CONSUMER_SECRET'),
        'access_token' => env('TWITTER_ACCESS_TOKEN'),
        'access_token_secret' => env('TWITTER_ACCESS_TOKEN_SECRET'),
    ],

    // config/services.php の配列に以下を追加

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'line' => [
        'client_id' => env('LINE_CLIENT_ID'),
        'client_secret' => env('LINE_CLIENT_SECRET'),
        'redirect' => env('LINE_REDIRECT_URI'),
        'messaging_token' => env('LINE_MESSAGING_CHANNEL_TOKEN'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // AIコンテンツ生成・検索の既定モデル。廃止対応は .env の ANTHROPIC_MODEL 1行で済む。
        // ※ 燃費OCR等でHaikuを別途指定している箇所はここの対象外。
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'rakuten' => [
        'app_id' => env('RAKUTEN_APP_ID'),
        'access_key' => env('RAKUTEN_ACCESS_KEY'),
        'affiliate_id' => env('RAKUTEN_AFFILIATE_ID'),
        // 楽天市場商品検索APIのエンドポイント。URL末尾の日付＝APIバージョンで、廃止されると全リクエストが
        // 400 (API Configuration not found) になる（2026-08-17 に旧バージョンが廃止され実際に発生）。
        // 次の廃止時に4箇所を探し回らず1箇所で切り替えられるよう、ここに一本化して env で上書き可能にする。
        'item_search_url' => env('RAKUTEN_ITEM_SEARCH_URL', 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701'),
        // RakutenRateGate の順番待ち上限（秒）。Web はユーザー応答を待たせないため短く（現状維持）、
        // CLI（parts:refresh 等のバッチ）は取りこぼし・429後の再開を優先して長く。
        'gate_max_wait_web' => env('RAKUTEN_GATE_MAX_WAIT_WEB', 0.5),
        'gate_max_wait_cli' => env('RAKUTEN_GATE_MAX_WAIT_CLI', 60),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

    'yahoo_shopping' => [
        'client_id' => env('YAHOO_CLIENT_ID'),
        'valuecommerce_sid' => env('VALUECOMMERCE_SID'),
        'valuecommerce_pid' => env('VALUECOMMERCE_PID'),
    ],

    'amazon' => [
        'associate_tag' => env('AMAZON_ASSOCIATE_TAG'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],
];
