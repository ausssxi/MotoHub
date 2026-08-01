<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 免許ガイド記事（/license/{class}）のアフィリエイト
|--------------------------------------------------------------------------
|
| 手数料セクション直後に、合宿免許の申込先へ送客するバナー枠を出す。★承認後に
| 発行URL・バナー画像URLを env に入れる。url と banner_url が両方揃うまで枠自体を
| 非表示（偽ボタン・空バナーを置かない）。driving_schools.affiliate / theft.affiliate
| と同型。二輪の合宿可否・料金・日程は申込先で確認させる文言にする（断定しない）。
| width/height は CLS 防止のため既定 300x250 を持たせる。
|
*/

return [
    'affiliate' => [
        'url' => env('LICENSE_AFFILIATE_URL', ''),
        // バナー画像URL。未設定なら枠自体を出さない。
        'banner_url' => env('LICENSE_AFFILIATE_BANNER_URL', ''),
        // 任意: インプレッション計測URL。設定時のみ 1x1 img を出す（未設定は出さない）。
        'imp_url' => env('LICENSE_AFFILIATE_IMP_URL', ''),
        // バナーの表示サイズ（CLS防止のため明示。既定 300x250）。
        'width' => env('LICENSE_AFFILIATE_WIDTH', 300),
        'height' => env('LICENSE_AFFILIATE_HEIGHT', 250),
        'provider' => env('LICENSE_AFFILIATE_PROVIDER', ''),
        // バナー img の alt。
        'alt' => env('LICENSE_AFFILIATE_ALT', ''),
    ],
];
