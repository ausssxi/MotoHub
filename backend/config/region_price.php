<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 地域差・独立ページ（/bikes/region-price）の設定
|--------------------------------------------------------------------------
| sitemap_enabled=false の間は sitemap-region-price.xml を出さない（=IndexNow送信もしない）。
| 本文・内部リンクが揃って目視OKするまで submit しない（thin signal回避）。
| 有効化すると GenerateSitemap::submitIndexNow の既存差分送信に自動で乗る。
*/

return [
    'sitemap_enabled' => env('REGION_PRICE_SITEMAP_ENABLED', false),

    /*
    | heterogeneity guard: 全国行の p90/p10 がこの倍率を超えるモデルは、現行＋ヴィンテージ等が
    | 1バケットに混在し中央値が無意味（例 z900 70〜110万 と 旧Z系 299〜489万）。
    | そのモデルは getForModel が空を返し、地域価格を一切主張しない（表/本文/featured/県LP/
    | region-priceページ を全面抑制）。p10/p90 が null のモデルはガード無効＝後方互換。
    */
    'heterogeneity_max_ratio' => (float) env('REGION_PRICE_HETEROGENEITY_MAX_RATIO', 3.0),
];
