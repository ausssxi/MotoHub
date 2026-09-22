<?php

declare(strict_types=1);

use App\Support\RentalBike\Fetchers\BikeCenterFetcher;
use App\Support\RentalBike\Fetchers\MotobaseFetcher;
use App\Support\RentalBike\Fetchers\Rental819Fetcher;

return [
    /*
    |--------------------------------------------------------------------------
    | レンタルバイク店舗の事業者パーサ（会社ごとに1クラス）
    |--------------------------------------------------------------------------
    | 会社を増やすときは Fetcher を1クラス足し、ここに company_slug => class を1行足すだけ。
    | ★画像・紹介文・在庫は扱わない（ウェビックの掲載停止の経緯。今後も扱わない）。
    */
    'fetchers' => [
        'bikecenter' => BikeCenterFetcher::class,
        'motobase' => MotobaseFetcher::class,
        'rental819' => Rental819Fetcher::class,
    ],
];
