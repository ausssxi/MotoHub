<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ブログ トピッククラスター定義（内部リンク用・コードが正本／DB非依存）
|--------------------------------------------------------------------------
|
| データ記事群を pillar（親＝網羅的なまとめ記事）⇄ members（子＝個別/分析記事）
| で束ねる。関連記事ブロック <x-blog-related> がこの定義を参照し、同クラスタ記事を
| 記事末尾に表示する（クローラビリティ向上・トピック評価の集約）。
|
| - slug はブログ記事の slug（/blog/{slug}）。
| - 1記事が複数クラスタに属してよい（例: rebel250 は 250cc と 相場 の両方）。
| - DB の series/tag とは独立（ここを編集すれば内部リンクに即反映・マイグレーション不要）。
|
*/

return [
    'clusters' => [
        '125cc' => [
            'label' => '125ccクラス',
            'pillar' => '125cc-all-models-comparison-2026',
            'members' => [
                'cygnus-x-price-surge-2026-05',
                'super-cub-50-after-discontinuation-2026',
            ],
        ],

        '250cc' => [
            'label' => '250ccクラス',
            'pillar' => '250cc-all-models-comparison-2026',
            'members' => [
                'rebel250-vs-gb350-2026',
                'best-bikes-for-beginners-2026',
                'y3vjg6tpp2hmo5', // 初心者におすすめのバイク10選
            ],
        ],

        '400cc' => [
            'label' => '400ccクラス',
            'pillar' => '400cc-all-models-comparison-2026',
            'members' => [
                'middleweight-price-surge-2026-05',
            ],
        ],

        'large_premium' => [
            'label' => '大型・プレミアム/旧車',
            'pillar' => 'rare-discontinued-used-bikes-2026',
            'members' => [
                'z900rs-premium-price-2026',
                'harley-vintage-discontinued-used-guide-2026',
                'gwdiu2f187niqv', // 特攻の拓
                'ehvgrc2cjs8vrh', // 東京リベンジャーズ
                'irzp9vam18uvxc', // ばくおん!!
            ],
        ],

        'market' => [
            'label' => '相場・値動き',
            'pillar' => 'bike-market-forecast-summer-2026',
            'members' => [
                'market-report-2026-03',
                'market-report-2026-04',
                'market-report-2026-05',
                'best-deals-bikes-2026-05',
                'z900rs-premium-price-2026',
                'cygnus-x-price-surge-2026-05',
                'super-cub-50-after-discontinuation-2026',
                'middleweight-price-surge-2026-05',
                'bh2knt14vf1acv', // 5分でできる相場チェック
                'vpbcc1mftpsizx', // バイクの売り時を見極める
                'frgpyu5wsgoc04', // 中古バイクの価格相場の見方
            ],
        ],

        'buying_data' => [
            'label' => 'データで選ぶ・損しない買い方',
            'pillar' => 'used-bike-buying-guide-2026',
            'members' => [
                'best-bikes-for-beginners-2026',
                'fastest-selling-bikes-2026',
                'frgpyu5wsgoc04',
                'mo92s6jy2ss8ea', // 中古バイクで失敗しないための5つのチェックポイント
                'bh2knt14vf1acv',
            ],
        ],

        'running_cost' => [
            'label' => '維持費・所有コスト',
            'pillar' => 'dmrn95nkr5ru0g', // バイクの年間維持費は？排気量別
            'members' => [
                'big-bike-annual-cost-comparison-2026',
                'gentsuki-oil-change',
                'gentsuki-battery',
            ],
        ],
    ],
];
