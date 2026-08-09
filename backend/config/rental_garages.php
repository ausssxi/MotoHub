<?php

declare(strict_types=1);

use App\Services\RentalGarage\Scrapers\InabaBoxScraper;
use App\Services\RentalGarage\Scrapers\KaseScraper;
use App\Services\RentalGarage\Scrapers\StorageOhScraper;

return [
    /*
    |--------------------------------------------------------------------------
    | レンタルガレージ スクレイパー登録
    |--------------------------------------------------------------------------
    | key => スクレイパークラス。社を足すときはこの1箇所に1行追加するだけでよい。
    | key は rental_garage:fetch --operator= に渡す識別子（AbstractRentalGarageScraper::key() と一致）。
    */
    'scrapers' => [
        'inaba' => InabaBoxScraper::class,
        'storageoh' => StorageOhScraper::class,
        'kase' => KaseScraper::class,
    ],
];
