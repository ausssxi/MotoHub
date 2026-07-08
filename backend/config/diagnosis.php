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
            'cause' => 'バッテリー上がり・弱り',
            'verdict' => 'diy',
            'advice' => "【確認】セルが「カチカチ」鳴る・ライトやウインカーが暗い・数週間乗っていなかった、ならバッテリー弱りが濃厚です。キックがある車種はキックで始動できるか試してください。\n【対処】キックで始動できたら30分ほど走ると充電されますが、繰り返すようなら寿命です（原付用は2〜5年が目安）。充電器があれば補充電も有効です。\n【交換】バッテリー交換は自分でも可能ですが、端子はプラスとマイナスの順番を守り、ショートに注意。型番は下の適合表から確認できます。",
            'article' => '/blog/gentsuki-battery',
            'article_anchor' => 'fix', // 66「自分で交換する手順」
            'parts_keyword' => 'バイク バッテリー',
            'parts_label' => 'バッテリーの価格を比較する',
            'fitment_task' => 'battery', // 型番（適合表）ページへの導線 task
        ],
        'gas_empty' => [
            'cause' => 'ガス欠・燃料不足',
            'verdict' => 'solved',
            'advice' => '給油する。コック付き車はコックの位置（ON/RES）も確認。',
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'fuel_carb' => [
            'cause' => '燃料劣化・キャブ詰まり',
            'verdict' => 'diy_then_shop',
            'advice' => "【確認】ガソリンが古くないか（3ヶ月以上放置で劣化）、燃料コックがONか、負圧コックの車種はホース抜けがないかを確認。かぶり気味なら、アクセル全開で数秒セルを回すと始動することがあります。\n【対処】古いガソリンが原因なら、新しいガソリンを足す・入れ替えると改善することがあります。キャブ車で長期放置なら、キャブ内のガソリン腐りが定番です。\n【店へ】キャブの分解清掃は工具と経験が必要です。ガソリンを扱う作業は火気厳禁・換気必須のため、自信がなければ店に任せるのが安全です。",
            'article' => '/blog/gentsuki-fuel-carb', // 専用記事（公開済み）
            'article_anchor' => 'additive',           // 「フューエルワンを試す」セクション
            'parts_keyword' => 'バイク 燃料添加剤 フューエルワン',
            'parts_label' => '燃料添加剤・キャブクリーナーの価格を比較する',
        ],
        'plug' => [
            'cause' => 'プラグ汚れ・かぶり・点火系',
            'verdict' => 'check_then_shop',
            'advice' => "【確認】プラグの寿命は3,000〜5,000kmが目安。最近交換した記憶がなく、始動性が徐々に悪化していたならプラグ劣化が疑わしいです。\n【対処】プラグ交換は プラグレンチがあれば自分でも可能です。外した電極が真っ黒（かぶり）・白く溶けている・すり減っているなら交換時期。型番は下の適合表から確認できます。\n【注意】締めすぎはネジ山を傷めます（手で締めた後、工具で1/2回転程度が目安）。外したまわりにゴミが落ちないよう注意してください。",
            'article' => '/blog/gentsuki-engine-wont-start',
            'parts_keyword' => 'バイク スパークプラグ',
            'parts_label' => 'スパークプラグの価格を比較する',
            'fitment_task' => 'plug', // 型番（適合表）ページへの導線 task
        ],
        'switch' => [
            'cause' => 'キルスイッチ／ブレーキ／ヒューズ等',
            'verdict' => 'diy',
            'advice' => "【確認】キルスイッチがRUN（⭕側）になっているか、スクーターはブレーキを握りながらセルを押しているか、サイドスタンドを払っているか（センサー付き車種）を順に確認してください。\n【対処】どれかが原因なら、正しい状態にすればそのまま始動できます。意外と多い定番なので、恥ずかしいことではありません。\n【次へ】全て問題ないのにかからない場合は、ヒューズ切れの確認を。ヒューズボックスの場所は取扱説明書に記載があります。切れていたら同じアンペア数のものに交換してください。",
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        'drivetrain' => [
            'cause' => '駆動系（ベルト・ウェイトローラー）',
            'verdict' => 'shop',
            'advice' => '消耗品の摩耗。分解整備が必要なので店（または上級者）へ。',
            'article' => '/blog/gentsuki-acceleration',
            'article_anchor' => 'drivetrain', // 68「原因①：駆動系の摩耗」
            'parts_keyword' => 'バイク ドライブベルト ウェイトローラー',
            'parts_label' => '駆動系パーツの価格を比較する',
        ],
        'air_filter' => [
            'cause' => 'エアクリーナー詰まり',
            'verdict' => 'diy_then_shop',
            'advice' => 'エアクリを清掃／交換。汚れがひどい・改善しなければ店で点検。',
            'article' => '/blog/gentsuki-acceleration',
            'article_anchor' => 'air-filter', // 68「原因②：エアクリーナーの詰まり」
            'parts_keyword' => 'バイク エアフィルター',
            'parts_label' => 'エアフィルターの価格を比較する',
        ],
        'tire' => [
            'cause' => 'パンク・空気圧不足',
            'verdict' => 'diy_then_shop',
            'advice' => 'チューブレスはパンク修理キットで応急可。チューブタイヤ／大きい穴・サイドウォール損傷は店へ。',
            'article' => '/blog/gentsuki-puncture',
            'article_anchor' => 'fix', // 67「自分で直す」
            'parts_keyword' => 'バイク パンク修理キット',
            'parts_label' => 'パンク修理キットの価格を比較する',
        ],
        'oil' => [
            'cause' => 'オイル不足・劣化',
            'verdict' => 'diy',
            'advice' => 'オイル量・汚れを確認し、不足／劣化なら交換。',
            'article' => '/blog/gentsuki-oil-change',
            'article_anchor' => 'fix', // 64「自分でやる場合の手順（4スト・ざっくり）」
            'parts_keyword' => 'バイク エンジンオイル',
            'parts_label' => 'エンジンオイルの価格を比較する',
            'fitment_task' => 'oil', // 型番（適合表）ページへの導線 task
        ],
        'cold' => [
            'cause' => '冬の低温（原因ではなく増幅要因）',
            'verdict' => 'diy',
            'advice' => "【確認】冬や冷え込んだ朝にかかりにくいのは、バッテリーの性能低下（低温で能力が2〜3割落ちます）と、オイルが硬くなることが主因です。特に2年以上使ったバッテリーは真っ先に疑ってください。\n【対処】セルを回す前にライトを数十秒点けてバッテリーを目覚めさせる、セルは数秒ずつ区切って回す（回しっぱなしにしない）のがコツです。キック併用車はキックが確実です。\n【交換】毎冬かかりが悪いならバッテリーが寿命間近のサインです。型番は下の適合表から確認できます。",
            'article' => '/blog/gentsuki-winter-wont-start',
            'article_anchor' => 'care', // 69「乗る前にできる冬対策」
            'fitment_task' => 'battery', // 冬の主因＝バッテリー弱り。結果画面から適合バッテリーへ
        ],
        'starter' => [
            'cause' => 'セルモーター等 始動系の故障',
            'verdict' => 'shop',
            'advice' => "【確認】セルが無音・無反応でも、まずキルスイッチがRUNか・ブレーキを握っているか（スクーター）・ヒューズ切れを確認。ライトが明るく点くのにセルだけ無反応なら、スターターリレーやセルモーター系の可能性が高いです。\n【対処】リレーの接点不良は軽く叩くと一時的に動くことがありますが、応急処置です。配線・モーターの特定には テスターと知識が必要なため、無理せず店へ。\n【店へ】キック始動できる車種は、キックで動かして店まで自走も可能です。",
            'article' => '/blog/gentsuki-engine-wont-start',
        ],
        // 電装・ヘッドライト。v1の6症状ツリーからは未到達の共有カード（oilと同じ扱い）。
        // 主目的は gentsuki-headlight 記事を「修理記事」として登録し trouble-cta を出すこと。
        'headlight' => [
            'cause' => 'ヘッドライト切れ・電装系',
            'verdict' => 'diy_then_shop',
            'advice' => 'バルブ切れを確認して交換。ヒューズ・配線・接触も点検。改善しなければ電装系の点検で店へ。',
            'article' => '/blog/gentsuki-headlight',
            'article_anchor' => 'fix', // 70「交換手順（ライブDIOを例に）」
            'parts_keyword' => 'バイク ヘッドライト バルブ',
            'parts_label' => 'ヘッドライトバルブの価格を比較する',
        ],
        // エンジン焼き付き。v1の6症状ツリーからは未到達の共有カード（oil/headlightと同じ扱い）。
        // 主目的は gentsuki-seizure 記事を「修理記事」として登録し trouble-cta を出すこと。
        'seizure' => [
            'cause' => 'エンジン焼き付き（オイル切れ・過熱）',
            'verdict' => 'shop',
            'advice' => "【すぐに】走行中に金属的な異音・急な重さ・パワーダウンを感じたら、ただちにエンジンを止めて安全な場所へ。かかっても再始動や走行を繰り返すと傷が広がるため、エンジンはかけ直さず、ロードサービスかレッカーでバイク店へ運んでください。\n【見分け】焼き付きの兆候は、急に止まった後セルもキックも重い・回らない、ガリガリ/キーンという異音、直前のオイル量の低下やオイル警告灯。思い当たれば無理に動かさないでください。\n【費用の目安】軽度なら数万円台から、ピストンやシリンダーまで及ぶと十数万円以上になることもあります。修理費が車両価値を超える場合は乗り換えも選択肢です。",
            'article' => '/blog/gentsuki-seizure',
            'fitment_task' => 'oil', // 焼き付き予防＝オイル管理。結果画面から車種別のオイル量・粘度へ
        ],
        // 出先で動かない時の対処。v1の6症状ツリーからは未到達の共有カード（oil/headlight/seizureと同じ扱い）。
        // 主目的は gentsuki-roadside 記事を「修理記事」として登録し trouble-cta を出すこと。
        'roadside' => [
            'cause' => '出先でバイクが動かない（立ち往生）',
            'verdict' => 'check_then_shop',
            'advice' => 'まず安全な場所へ退避。ガス欠・キルスイッチ・ヒューズ等の基本を確認し、直らなければJAF・ロードサービス・店へ。',
            'article' => '/blog/gentsuki-roadside',
        ],
        'unknown' => [
            'cause' => '切り分けても原因不明・無反応',
            'verdict' => 'shop',
            'advice' => 'プロへ。「セルが回る／回らない」「いつ・どんな時に起きるか」を伝える。',
            'article' => null,
        ],
    ],

    // ── verdict 表示メタ（バッジのラベルと色） ──
    'verdicts' => [
        'diy' => ['label' => '自分で対処できそう',         'class' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
        'diy_then_shop' => ['label' => 'まず自分で→ダメなら店',      'class' => 'bg-teal-100 text-teal-700 ring-teal-200'],
        'check_then_shop' => ['label' => '簡単な確認→改善なければ店',   'class' => 'bg-amber-100 text-amber-700 ring-amber-200'],
        'shop' => ['label' => '店・上級者の領域',           'class' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        'solved' => ['label' => '単純な原因で解決',           'class' => 'bg-blue-100 text-blue-700 ring-blue-200'],
    ],

    // ── 症状の入口（6）→ ルートノード ──
    'symptoms' => [
        'engine-wont-start' => ['label' => 'エンジンがかからない',       'emoji' => '🔑', 'root' => 'start__gate'],
        'stalling' => ['label' => 'エンジンが止まる・エンスト', 'emoji' => '🛑', 'root' => 'stall__when'],
        'battery' => ['label' => 'バッテリー上がり',           'emoji' => '🔋', 'root' => 'batt__symptom'],
        'puncture' => ['label' => 'パンク',                     'emoji' => '🛞', 'root' => 'punc__type'],
        'no-accel' => ['label' => '加速しない',                 'emoji' => '🐌', 'root' => 'accel__onset'],
        'winter' => ['label' => '冬にかからない',             'emoji' => '❄️', 'root' => 'winter__main'],
        // 孤児カード（headlight/seizure/roadside）を診断に露出させる2症状
        'lights' => ['label' => 'ライト・電装がおかしい',     'emoji' => '💡', 'root' => 'lights__scope'],
        'stranded' => ['label' => '出先で動かなくなった',       'emoji' => '🆘', 'root' => 'stranded__safety'],
    ],

    // ── 質問ノード（使い回し）。option: card(終端) または next(別ノード) ──
    'nodes' => [

        // ① エンジンがかからない
        'start__gate' => [
            'question' => 'まず基本の確認。当てはまるものはありますか？',
            'help' => 'キルスイッチOFF・スクーターのブレーキ未操作・ガス欠は「かからない」の定番原因です。 ※ 「かかりにくい」「かかっても不安定」な場合も、当てはまる項目から進めてください。原因は同じことが多いです。',
            'options' => [
                ['label' => 'キルスイッチがOFF / ブレーキを握っていなかった', 'card' => 'switch'],
                ['label' => 'ガソリンが入っていない',                        'card' => 'gas_empty'],
                ['label' => 'どれも問題ない（確認済み）',                    'next' => 'start__crank'],
            ],
        ],
        'start__crank' => [
            'question' => 'セルを回したときの音は？',
            'help' => '走行中の異音や急停止の直後で、セル・キックが重い場合は焼き付きの可能性があります。',
            'options' => [
                ['label' => 'カチカチと鳴る／元気がない（キックならかかる）', 'card' => 'battery'],
                ['label' => '無音・無反応（セルが全く反応しない。押し歩きは重くない）', 'card' => 'starter'],
                ['label' => '走行中に異音がして急に止まり、その後セルもキックも重い・回らない', 'card' => 'seizure'],
                ['label' => 'キュルキュル元気に回るのに、かからない',         'next' => 'start__fuel'],
                // キックのみ車（セル無し）はセル/バッテリー始動系に該当しないため燃料・プラグ系へ
                ['label' => 'この車はキックのみ（セルが無い）／セルを使わない', 'next' => 'start__fuel'],
            ],
        ],
        'start__fuel' => [
            'question' => 'ガソリンの状態は？',
            'options' => [
                ['label' => '古い／数週間以上 放置していた', 'card' => 'fuel_carb'],
                ['label' => '新しい・最近給油した',         'card' => 'plug'],
                ['label' => 'わからない',                   'card' => 'unknown'],
            ],
        ],

        // ② エンジンが止まる・エンスト
        'stall__when' => [
            'question' => 'どんな時に止まりますか？',
            'help' => '信号待ちやアイドリングで落ちるなら燃料・キャブが最有力です。',
            'options' => [
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
            'options' => [
                ['label' => 'セルが弱い・カチカチ鳴る',         'card' => 'battery'],
                ['label' => '充電してもすぐ上がる（寿命っぽい）', 'card' => 'battery'],
                ['label' => '充電してもセルが無反応',           'card' => 'starter'],
            ],
        ],

        // ④ パンク
        'punc__type' => [
            'question' => 'タイヤの種類・穴の状態は？',
            'help' => 'チューブレスならキットで応急可。チューブや大穴は店が安全です。',
            'options' => [
                ['label' => 'チューブレス（多くの原付・スクーター）',     'card' => 'tire'],
                ['label' => 'チューブタイヤ（スポークホイール・カブ系）', 'card' => 'tire'],
                ['label' => '大きい穴／サイドウォールが切れている',       'card' => 'tire'],
            ],
        ],

        // ⑤ 加速しない
        'accel__onset' => [
            'question' => 'どんなふうに加速しませんか？',
            'options' => [
                ['label' => '徐々に最高速が落ちた／ベルトが滑る感触', 'card' => 'drivetrain'],
                ['label' => '吹け上がりが悪い・息継ぎする',           'next' => 'accel__cause'],
                ['label' => '急に力が出なくなった',                   'next' => 'accel__cause'],
                ['label' => 'わからない',                             'card' => 'unknown'],
            ],
        ],
        'accel__cause' => [
            'question' => '症状に近いのは？',
            'options' => [
                ['label' => '加速時にモタつく（吸気が怪しい）',   'card' => 'air_filter'],
                ['label' => '特定の回転で力が出ない（点火系）',   'card' => 'plug'],
                ['label' => '始動性も悪い・ガソリンが古い（燃料）', 'card' => 'fuel_carb'],
                ['label' => 'オイルを長く替えていない・量が減っている', 'card' => 'oil'],
                ['label' => 'わからない',                         'card' => 'unknown'],
            ],
        ],

        // ⑥ 冬にかからない
        'winter__main' => [
            'question' => '冬の始動不良。一番近いのは？',
            'help' => '冬はバッテリー弱りが主犯になりがち。低温は不調を増幅します。',
            'options' => [
                ['label' => 'セルが弱い・回りが鈍い（冬の主犯）',     'card' => 'battery'],
                ['label' => 'セルは元気だがかからない（チョーク／保管）', 'card' => 'cold'],
                ['label' => 'ガソリンが古い・放置していた',           'card' => 'fuel_carb'],
                ['label' => 'キルスイッチ／ブレーキ等の操作ミス',     'card' => 'switch'],
                ['label' => 'わからない',                             'card' => 'unknown'],
            ],
        ],

        // ⑦ ライト・電装がおかしい（孤児カード headlight を露出）
        'lights__scope' => [
            'question' => 'どんな症状に近いですか？',
            'help' => 'ヘッドライトだけならバルブ切れ、全体的に弱いならバッテリーが定番です。',
            'options' => [
                ['label' => 'ヘッドライトが点かない・暗い',                       'card' => 'headlight'],
                ['label' => 'ウインカー・ホーンなども含め全体的に弱い／おかしい', 'card' => 'battery'],
                ['label' => '特定の操作でヒューズが飛ぶ・スイッチ系が怪しい',     'card' => 'switch'],
                ['label' => 'わからない',                                         'card' => 'unknown'],
            ],
        ],

        // ⑧ 出先で動かなくなった（孤児カード seizure/roadside を露出）
        'stranded__safety' => [
            'question' => 'まず安全な場所へ移動を。状況に近いのは？',
            'help' => '路上での作業は危険です。安全確保が最優先。',
            'options' => [
                ['label' => 'ガソリンが無い／怪しい',                             'card' => 'gas_empty'],
                ['label' => '走行中に異音がして急に止まった（焼き付きの疑い）',   'card' => 'seizure'],
                ['label' => 'キルスイッチ・ブレーキ・ヒューズ等の基本を確認しても動かない', 'card' => 'roadside'],
                ['label' => 'わからない',                                         'card' => 'roadside'],
            ],
        ],
    ],
];
