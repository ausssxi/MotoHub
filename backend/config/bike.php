<?php

return [
    // 地域データ
    'regions' => [
        '北海道・東北' => ['北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島'],
        '関東' => ['茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川'],
        '中部' => ['新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜', '静岡', '愛知'],
        '近畿' => ['三重', '滋賀', '京都', '大阪', '兵庫', '奈良', '和歌山'],
        '中国・四国' => ['鳥取', '島根', '岡山', '広島', '山口', '徳島', '香川', '愛媛', '高知'],
        '九州・沖縄' => ['福岡', '佐賀', '長崎', '熊本', '大分', '宮崎', '鹿児島', '沖縄'],
    ],

    // 免許区分（排気量）の定義
    'licenses' => [
        [
            'label' => '原付 (~50cc)',
            'icon' => 'bike',
            'slug' => '50',
            'color' => 'bg-teal-50 text-teal-600',
        ],
        [
            'label' => '小型二種 (~125cc)',
            'icon' => 'bike',
            'slug' => '125',
            'color' => 'bg-pink-50 text-pink-600',
        ],
        [
            'label' => '軽二輪 (~250cc)',
            'icon' => 'bike',
            'slug' => '250',
            'color' => 'bg-sky-50 text-sky-600',
        ],
        [
            'label' => '中型 (~400cc)',
            'icon' => 'bike',
            'slug' => '400',
            'color' => 'bg-amber-50 text-amber-600',
        ],
        [
            'label' => 'ナナハン (~750cc)',
            'icon' => 'bike',
            'slug' => '750',
            'color' => 'bg-orange-50 text-orange-600',
        ],
        [
            'label' => '大型 (750cc超)',
            'icon' => 'bike',
            'slug' => 'over750',
            'color' => 'bg-purple-50 text-purple-600',
        ],
    ],

    // チェーン店定義（shops.name の LIKE 検索で判別）
    // pattern（文字列）or patterns（別名配列）で店名マッチ。判定は Shop::chainSlug()＝
    // 全角→半角・小文字化・空白除去で正規化してから部分一致（表記ゆれ吸収）。
    // カタカナ↔英字の橋渡しが要るチェーン（リバースオート/SOX/レッドバロン）は patterns に英字別名を併記。
    'chains' => [
        'red-baron'   => ['name' => 'レッドバロン', 'patterns' => ['レッドバロン', 'red baron']],
        'bikeo'       => ['name' => 'バイク王',     'pattern' => 'バイク王'],
        'bikekan'     => ['name' => 'バイク館',     'pattern' => 'バイク館', 'guide_slug' => 'bikekan-used-bike-guide-2026'],
        'scs'         => ['name' => 'SCS',          'pattern' => 'SCS'],
        'naps'        => ['name' => 'ナップス',     'pattern' => 'ナップス'],
        'ricoland'    => ['name' => 'ライコランド', 'pattern' => 'ライコランド'],
        'sox'         => ['name' => 'バイカーズステーションSOX', 'patterns' => ['ソックス', 'sox']],
        'bikeland'    => ['name' => 'バイクランド', 'pattern' => 'バイクランド'],
        'honda-dream' => ['name' => 'ホンダドリーム', 'pattern' => 'ホンダドリーム'],
        'kawasaki-plaza' => ['name' => 'カワサキプラザ', 'pattern' => 'カワサキプラザ'],
        'ysp'         => ['name' => 'YSP',         'pattern' => 'YSP'],
        'sbs'         => ['name' => 'SBS（スズキバイクショップ）', 'pattern' => 'ＳＢＳ'],
        // 追加（在庫確認済）。REVERSE AUTO / Reverse Auto の英字表記も吸収。
        'reverse-auto' => ['name' => 'リバースオート', 'patterns' => ['リバースオート', 'reverse auto']],
    ],

    // ランキングの設定
    'ranking' => [
        'top_page_limit' => 16,
        'excluded_names' => [
            '他車種',
        ],
    ],
];