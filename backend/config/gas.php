<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ガソリンスタンドの運営ブランド（地図ピンの色分け）
    |--------------------------------------------------------------------------
    | Poi(type=gas_station) の brand カラム（OSMインポート・表記ゆれ多数）を分類する。
    | 判定は Poi::gasBrand()。戻り値:
    |   'eneos'|'idemitsu'|'cosmo'|'ja-ss'|'hokuren'|'kygnus'|'solato' … 固有色ブランド
    |   'other'   … 実在の独立系GS屋号（共通色バッジ＋生屋号ポップアップ）
    |   'exclude' … 明確な非GS（下記 exclude）。GS層ペイロードから除外
    |   null      … brand 空/不明。従来の赤⛽のまま（除外しない）
    |
    | patterns は ShopNameNormalizer::normalize（全半角統一・ひらがな→カナ・法人格除去・小文字化）
    | を通した後の部分一致。実データに存在する表記のみを列挙している（憶測で綴りを足さない）。
    | 未知の綴りは安全側で 'other'（＝グレーバッジ）に落ちる設計。
    |
    | ★ja-ss と cosmo は部分一致だと誤爆する（JA→JAF/latin ja、cosmo→コスモス薬品）ため
    |   Poi::gasBrand() 内の専用ガードで判定する（この patterns には載せない）。
    | ★三菱: 「三菱石油」は歴史的に ENEOS 系なので完全綴りで eneos に束ねる。
    |   「三菱商事エネルギー/三菱商事石油/三菱」は別物（販売会社）＝ patterns に入れず 'other'。
    */
    'brands' => [
        'eneos' => [
            'name' => 'ENEOS',
            'patterns' => [
                'eneos', 'エネオス', 'enejet', 'エネジェット', 'enedy', 'enesta', 'eneosフロンティア',
                // 2019 EMG統合（Esso/Mobil/ゼネラル）
                'esso', 'エッソ', 'mobil', 'モービル', 'ゼネラル', 'general',
                // 旧JX/JOMO/日石系（現ENEOS）
                'jomo', '日石', 'nisseki', '太陽鉱油', 'jxtg', '新日本石油', '新日石', '日鉱', 'ジャパンエナジー',
                // 旧三菱石油（現ENEOS）※「三菱商事*」とは完全綴りで区別
                '三菱石油',
            ],
        ],
        'idemitsu' => [
            'name' => '出光',
            'patterns' => [
                '出光', 'idemitsu', 'apollostation', 'アポロステーション',
                // 2019 昭和シェル統合
                '昭和シェル', 'shell', 'シェル', 'ダイヤ昭石',
            ],
        ],
        'cosmo' => [
            'name' => 'コスモ',
            // 実照合は gasBrand() の cosmo ガード（コスモ / latin cosmo〈cosmos除外〉）
            'patterns' => [],
        ],
        'ja-ss' => [
            'name' => 'JA-SS',
            // 実照合は gasBrand() の JA ガード（latin誤爆防止）
            'patterns' => [],
        ],
        'hokuren' => [
            'name' => 'ホクレン',
            'patterns' => ['ホクレン', 'hokuren'],
        ],
        'kygnus' => [
            'name' => 'キグナス',
            'patterns' => ['キグナス', 'kygnus'],
        ],
        'solato' => [
            'name' => '太陽石油',
            'patterns' => ['solato', 'sorato', 'solalt', '太陽石油', 'ソラト'],
        ],
    ],

    /*
    | 明確な非GS（GS層の誤ラベル）→ ペイロードから除外。正規化後の部分一致。
    | ハングル表記（GS칼텍스/SK주유소/현대오일뱅크/에쓰오일 等）は文字種で一括除外（gasBrand内）。
    | 「迷う実在GS屋号」はここに入れない＝残して 'other'。
    */
    'exclude' => [
        'bing', 'navi',
        'lawson', 'ローソン', '7-eleven', 'セブンイレブン', 'コストコ', 'costco', 'kirkland',
        'isuzu', 'いすゞ', 'オートバックス', 'autobacs', 'ビバホーム', 'イエローハット',
        '水素', 'iwatani', '岩谷', 'イワタニ',
        '東京ガス', '日本ガス', 'アストモス', '都市ガス', '充電',
    ],
];
