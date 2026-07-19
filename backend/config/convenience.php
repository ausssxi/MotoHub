<?php

return [
    /*
    |--------------------------------------------------------------------------
    | コンビニの運営ブランド（地図ピンの色分け）
    |--------------------------------------------------------------------------
    | Poi(type=convenience_store) の brand を分類する（Poi::cvsBrand()）。GS(config/gas.php)と同型。
    | 戻り値:
    |   'seven'|'familymart'|'lawson'|'ministop'|'daily-yamazaki'|'seicomart'|'newdays' … 固有色
    |   'other'   … その他チェーン（ポプラ/Heart・in 等）＝共通色バッジ＋生屋号
    |   'exclude' … 閉店タグ(disused:) → ペイロードから除外
    |   null      … brand 空/不明 → 従来のアイコン
    |
    | patterns は ShopNameNormalizer::normalize 通過後の部分一致。実データにある表記のみ。
    |
    | ★束ね方針:
    |   - seven: ハイフン有無の揺れを吸収するため latin「eleven」/カナ「イレブン」で拾う。
    |   - lawson: 「lawson」/「ローソン」で STORE100・NATURAL・スリーエフ・ポプラ・toks 等の
    |     ローソン派生を全部束ねる（LAWSON+ポプラ もこちら。ポプラ"単独"だけ other）。
    |   - familymart: サークルK/サンクス（2016ファミマ統合）も familymart に束ねる。
    */
    'brands' => [
        'seven' => [
            'name' => 'セブン-イレブン',
            'patterns' => ['eleven', 'イレブン'],
        ],
        'familymart' => [
            'name' => 'ファミリーマート',
            // サークルK/サンクスは統合済みのため familymart に束ねる
            'patterns' => ['familymart', 'ファミリーマート', 'サークルk', 'サンクス', 'sunkus', 'sankus'],
        ],
        'lawson' => [
            'name' => 'ローソン',
            'patterns' => ['lawson', 'ローソン'],
        ],
        'ministop' => [
            'name' => 'ミニストップ',
            'patterns' => ['ministop', 'ミニストップ', 'mini-stop'],
        ],
        'daily-yamazaki' => [
            'name' => 'デイリーヤマザキ',
            'patterns' => ['dailyyamazaki', 'デイリーヤマザキ'],
        ],
        'seicomart' => [
            'name' => 'セイコーマート',
            'patterns' => ['seicomart', 'セイコーマート'],
        ],
        'newdays' => [
            'name' => 'NewDays',
            'patterns' => ['newdays'],
        ],
    ],

    // 閉店タグのみ除外（実データのノイズは disused: だけ）。
    'exclude' => [
        'disused',
    ],
];
