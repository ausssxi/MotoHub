<?php

declare(strict_types=1);

use App\Support\RentalBike\Fetchers\AjOsakaFetcher;
use App\Support\RentalBike\Fetchers\BikeCenterFetcher;
use App\Support\RentalBike\Fetchers\MotobaseFetcher;
use App\Support\RentalBike\Fetchers\NirinshoFetcher;
use App\Support\RentalBike\Fetchers\Rental819Fetcher;
use App\Support\RentalBike\Fetchers\YamahaFetcher;
use App\Support\RentalBike\PriceFetchers\NirinshoPriceFetcher;
use App\Support\RentalBike\PriceFetchers\YamahaPriceFetcher;

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
        'yamaha' => YamahaFetcher::class,
        'aj-osaka' => AjOsakaFetcher::class,
        'nirinsho' => NirinshoFetcher::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 料金・車種への公式リンク（第1段階：データは取得せず公式へ誘導するだけ）
    |--------------------------------------------------------------------------
    | 店舗詳細ページに「車種・料金」導線ボタンを出すための会社別設定。
    |   mode=per_shop : 店舗の official_url が「その店の車種・料金ページ」を兼ねる
    |                   （819=/store/{id}、AJ=shop_bike.php?shop={id}）。URLは持たない。
    |   mode=common   : 料金・車種が全社共通ページ。事業者の料金ページURLをここに持つ
    |                   （ヤマハ・二輪処）。
    | 未登録の会社（bikecenter/motobase 等）はボタンを出さない（従来の「公式サイトで見る」のみ）。
    | docs/plans/rental-bike-pricing.md 参照。
    */
    'pricing_links' => [
        'rental819' => ['mode' => 'per_shop'],
        'aj-osaka' => ['mode' => 'per_shop'],
        'yamaha' => ['mode' => 'common', 'url' => 'https://bike-rental.yamaha-motor.co.jp/jp/bike/info/fee'],
        'nirinsho' => ['mode' => 'common', 'url' => 'https://www.bike-rental.jp/price/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 料金フェッチャ（第2段階：車格別の参考価格を取り込む）
    |--------------------------------------------------------------------------
    | 会社を増やすときは PriceFetcher を1クラス足し、ここに company_slug => class を1行足す。
    | ★2-2 は ヤマハ・二輪処 のみ。819 は P-class の定義が判明してから追加する。
    | ★AJ OSAKA は車格別の統一料金表が無いため対象外（第1段階の公式リンクのまま）。
    */
    'price_fetchers' => [
        'yamaha' => YamahaPriceFetcher::class,
        'nirinsho' => NirinshoPriceFetcher::class,
    ],

    /*
    | 819 の P-class → MotoHub車格 の対応表（P-class の定義が判明したら埋める）。
    | 空の間は 819 の料金を取り込まない。またがりは下位車格へ寄せる方針（docs 参照）。
    */
    'price_class_map' => [
        'rental819' => [],
    ],
];
