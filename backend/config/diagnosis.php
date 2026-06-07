<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 症状自己診断ツール（トラブル診断）データ
|--------------------------------------------------------------------------
| ルールベースの決定木。全ての答えは事前authored ＝ ハルシネーションなし・
| 即答・APIコストなし・安全。
|
| フロー: 症状(入口) → 質問(nodes/使い回し) → 答えカード(cards/共有) → 記事
|
| nodes の option は終端なら `card`（cards のキー）、続きがあれば `next`
| （別 node のキー）を持つ。card を持つ option が「葉(leaf)」。
*/

return [

    // ── 共有答えカード（12枚）。複数の症状から同じカードへ収束する ──
    // verdict 凡例: diy / diy_then_shop / check_then_shop / shop / solved
    // article は null 許容（記事未作成のカードは結果画面でCTAを出さない）
    'cards' => [
        'battery' => [
            'cause'   => 'バッテリー上がり・弱り',
            'verdict' => 'diy',
            'advice'  => '充電・ジャンプ、キックで応急。繰り返すなら交換（寿命2〜3年）。',
            'article' => '/blog/gentsuki-battery',
        ],
        'gas_empty' => [
            'cause'   => 'ガス欠・燃料不足',
            'verdict' => 'solved',
            'advice'  => '給油する。コック付き車はコックの位置（ON/RES）も確認。',
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'fuel_carb' => [
            'cause'   => '燃料劣化・キャブ詰まり',
            'verdict' => 'diy_then_shop',
            'advice'  => '新しいガソリン＋フューエルワン投入。改善しなければキャブ清掃＝店。',
            'article' => '/blog/gyro-canopy-idle-stall',
        ],
        'plug' => [
            'cause'   => 'プラグ汚れ・かぶり・点火系',
            'verdict' => 'check_then_shop',
            'advice'  => 'プラグを外して状態を確認（濡れ・煤）。清掃／交換で改善しなければ店。',
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'switch' => [
            'cause'   => 'キルスイッチ／ブレーキ／ヒューズ等',
            'verdict' => 'diy',
            'advice'  => 'キルスイッチをRUNへ、スクーターはブレーキを握って始動、ヒューズも確認。',
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'drivetrain' => [
            'cause'   => '駆動系（ベルト・ウェイトローラー）',
            'verdict' => 'shop',
            'advice'  => '消耗品の摩耗。分解整備が必要なので店（または上級者）へ。',
            'article' => '/blog/gentsuki-acceleration',
        ],
        'air_filter' => [
            'cause'   => 'エアクリーナー詰まり',
            'verdict' => 'diy_then_shop',
            'advice'  => 'エアクリを清掃／交換。汚れがひどい・改善しなければ店で点検。',
            'article' => '/blog/gentsuki-acceleration',
        ],
        'tire' => [
            'cause'   => 'パンク・空気圧不足',
            'verdict' => 'diy_then_shop',
            'advice'  => 'チューブレスはパンク修理キットで応急可。チューブタイヤ／大きい穴・サイドウォール損傷は店へ。',
            'article' => '/blog/gentsuki-puncture',
        ],
        'oil' => [
            'cause'   => 'オイル不足・劣化',
            'verdict' => 'diy',
            'advice'  => 'オイル量・汚れを確認し、不足／劣化なら交換。',
            'article' => '/blog/gentsuki-oil-change',
        ],
        'cold' => [
            'cause'   => '冬の低温（原因ではなく増幅要因）',
            'verdict' => 'diy',
            'advice'  => 'チョークを使う、バッテリーを温める、屋内・カバーで保管を見直す。',
            'article' => '/blog/gentsuki-winter-wont-start',
        ],
        'starter' => [
            'cause'   => 'セルモーター等 始動系の故障',
            'verdict' => 'shop',
            'advice'  => '店へ。「セルが回る／回らない」など症状を具体的に伝えるとスムーズ。',
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'unknown' => [
            'cause'   => '切り分けても原因不明・無反応',
            'verdict' => 'shop',
            'advice'  => 'プロへ。「セルが回る／回らない」「いつ・どんな時に起きるか」を伝える。',
            'article' => null,
        ],
    ],

    // ── verdict 表示メタ（バッジのラベルと色） ──
    'verdicts' => [
        'diy'             => ['label' => '自分で対処できそう',         'class' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
        'diy_then_shop'   => ['label' => 'まず自分で→ダメなら店',      'class' => 'bg-teal-100 text-teal-700 ring-teal-200'],
        'check_then_shop' => ['label' => '簡単な確認→改善なければ店',   'class' => 'bg-amber-100 text-amber-700 ring-amber-200'],
        'shop'            => ['label' => '店・上級者の領域',           'class' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        'solved'          => ['label' => '単純な原因で解決',           'class' => 'bg-blue-100 text-blue-700 ring-blue-200'],
    ],

    // ── 症状の入口（6）→ ルートノード ──
    'symptoms' => [
        'engine-wont-start' => ['label' => 'エンジンがかからない',       'emoji' => '🔑', 'root' => 'start__gate'],
        'stalling'          => ['label' => 'エンジンが止まる・エンスト', 'emoji' => '🛑', 'root' => 'stall__when'],
        'battery'           => ['label' => 'バッテリー上がり',           'emoji' => '🔋', 'root' => 'batt__symptom'],
        'puncture'          => ['label' => 'パンク',                     'emoji' => '🛞', 'root' => 'punc__type'],
        'no-accel'          => ['label' => '加速しない',                 'emoji' => '🐌', 'root' => 'accel__onset'],
        'winter'            => ['label' => '冬にかからない',             'emoji' => '❄️', 'root' => 'winter__main'],
    ],

    // ── 質問ノード（使い回し）。option: card(終端) または next(別ノード) ──
    'nodes' => [

        // ① エンジンがかからない
        'start__gate' => [
            'question' => 'まず基本の確認。当てはまるものはありますか？',
            'help'     => 'キルスイッチOFF・スクーターのブレーキ未操作・ガス欠は「かからない」の定番原因です。',
            'options'  => [
                ['label' => 'キルスイッチがOFF / ブレーキを握っていなかった', 'card' => 'switch'],
                ['label' => 'ガソリンが入っていない',                        'card' => 'gas_empty'],
                ['label' => 'どれも問題ない（確認済み）',                    'next' => 'start__crank'],
            ],
        ],
        'start__crank' => [
            'question' => 'セルを回したときの音は？',
            'options'  => [
                ['label' => 'カチカチ／弱い・回らない（キックならかかる）', 'card' => 'battery'],
                ['label' => '無反応のまま（うんともすんとも）',             'card' => 'starter'],
                ['label' => 'キュルキュル元気に回るのにかからない',         'next' => 'start__fuel'],
            ],
        ],
        'start__fuel' => [
            'question' => 'ガソリンの状態は？',
            'options'  => [
                ['label' => '古い／数週間以上 放置していた', 'card' => 'fuel_carb'],
                ['label' => '新しい・最近給油した',         'card' => 'plug'],
                ['label' => 'わからない',                   'card' => 'unknown'],
            ],
        ],

        // ② エンジンが止まる・エンスト
        'stall__when' => [
            'question' => 'どんな時に止まりますか？',
            'help'     => '信号待ちやアイドリングで落ちるなら燃料・キャブが最有力です。',
            'options'  => [
                ['label' => '停車・アイドリング中に多い',           'card' => 'fuel_carb'],
                ['label' => '加速時にモタついて止まる',             'card' => 'air_filter'],
                ['label' => '寒い日・冬に多い',                     'card' => 'cold'],
                ['label' => 'エンジン不調・力が出ない（プラグ兆候）', 'card' => 'plug'],
                ['label' => 'わからない',                           'card' => 'unknown'],
            ],
        ],

        // ③ バッテリー上がり
        'batt__symptom' => [
            'question' => 'バッテリーの症状に近いのは？',
            'options'  => [
                ['label' => 'セルが弱い・カチカチ鳴る',         'card' => 'battery'],
                ['label' => '充電してもすぐ上がる（寿命っぽい）', 'card' => 'battery'],
                ['label' => '充電してもセルが無反応',           'card' => 'starter'],
            ],
        ],

        // ④ パンク
        'punc__type' => [
            'question' => 'タイヤの種類・穴の状態は？',
            'help'     => 'チューブレスならキットで応急可。チューブや大穴は店が安全です。',
            'options'  => [
                ['label' => 'チューブレス（多くの原付・スクーター）',     'card' => 'tire'],
                ['label' => 'チューブタイヤ（スポークホイール・カブ系）', 'card' => 'tire'],
                ['label' => '大きい穴／サイドウォールが切れている',       'card' => 'tire'],
            ],
        ],

        // ⑤ 加速しない
        'accel__onset' => [
            'question' => 'どんなふうに加速しませんか？',
            'options'  => [
                ['label' => '徐々に最高速が落ちた／ベルトが滑る感触', 'card' => 'drivetrain'],
                ['label' => '吹け上がりが悪い・息継ぎする',           'next' => 'accel__cause'],
                ['label' => '急に力が出なくなった',                   'next' => 'accel__cause'],
                ['label' => 'わからない',                             'card' => 'unknown'],
            ],
        ],
        'accel__cause' => [
            'question' => '症状に近いのは？',
            'options'  => [
                ['label' => '加速時にモタつく（吸気が怪しい）',   'card' => 'air_filter'],
                ['label' => '特定の回転で力が出ない（点火系）',   'card' => 'plug'],
                ['label' => '始動性も悪い・ガソリンが古い（燃料）', 'card' => 'fuel_carb'],
                ['label' => 'わからない',                         'card' => 'unknown'],
            ],
        ],

        // ⑥ 冬にかからない
        'winter__main' => [
            'question' => '冬の始動不良。一番近いのは？',
            'help'     => '冬はバッテリー弱りが主犯になりがち。低温は不調を増幅します。',
            'options'  => [
                ['label' => 'セルが弱い・回りが鈍い（冬の主犯）',     'card' => 'battery'],
                ['label' => 'セルは元気だがかからない（チョーク／保管）', 'card' => 'cold'],
                ['label' => 'ガソリンが古い・放置していた',           'card' => 'fuel_carb'],
                ['label' => 'キルスイッチ／ブレーキ等の操作ミス',     'card' => 'switch'],
                ['label' => 'わからない',                             'card' => 'unknown'],
            ],
        ],
    ],
];
