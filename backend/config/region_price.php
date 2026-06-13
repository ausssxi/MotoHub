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
];
