<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 保険コーナー（/hoken・車種ページ維持費ブロック）の固定額データ
|--------------------------------------------------------------------------
| ★正直さの生命線: ここに入れるのは「公的な固定額」だけ。任意保険の保険料は
|   人・条件で変わるため一切創作しない（金額は出さず一括見積もりCTAへ誘導）。
| ★出典と最終確認日を必ず表示側に出す。数値は下記ソースから転記・裏取り済。
|
| 裏取り（2026-07 実施）:
|  - 軽自動車税(種別割) 標準税率: 総務省 地方税制度 / 自治体公表税額表で確認
|    https://www.soumu.go.jp/main_sosiki/jichi_zeisei/czaisei/czaisei_seido/149767_13.html
|  - 自賠責保険 基準料率(本土・2024年1月届出・2026年10月31日まで適用):
|    損害保険料率算出機構 https://www.giroj.or.jp/publication/cali_table.html
|    （12ヶ月の原付6,910/軽二輪7,100/小型二輪7,010 を一次情報と突合済）
| ★2026年11月1日に自賠責改定予定（全車種平均約6.2%引上げ）。改定後の確定額は
|   一次情報が出てから revision 側を埋めて追従（現時点では注記のみ・数字は出さない）。
*/

return [
    'slug' => 'hoken',

    // 表示側に出す「最終確認: YYYY/MM」
    'last_verified' => '2026-07',

    'sources' => [
        'tax' => [
            'name' => '総務省（地方税法・軽自動車税 種別割 標準税率）',
            'url' => 'https://www.soumu.go.jp/main_sosiki/jichi_zeisei/czaisei/czaisei_seido/149767_13.html',
        ],
        'jibaiseki' => [
            'name' => '損害保険料率算出機構（自賠責保険 基準料率表）',
            'url' => 'https://www.giroj.or.jp/publication/cali_table.html',
        ],
    ],

    // 自賠責の2026年11月改定。現行額を出す区分には必ずこの注記を添える。
    'jibaiseki_revision_note' => '自賠責保険料は2026年11月1日に改定予定（全車種平均で約6.2%引き上げ）。掲載している額は2026年10月31日までの現行料率です。改定後の額は確定後に反映します。',

    // アフィリエイト（一括見積もり）。★ASP承認待ちのため url 未設定の間は CTA 自体を非表示。
    // 承認後にここへ URL を入れてデプロイすれば、コード変更なしで CTA が差し込まれる。
    'affiliate' => [
        'url' => env('INSURANCE_AFFILIATE_URL', ''),
        'provider' => env('INSURANCE_AFFILIATE_PROVIDER', ''), // 例: 保険スクエアbang! 等（承認後に表記）
        // 事実ベースの文言のみ（誇大表現・保険募集にあたる比較/推奨はしない）。
        // ★相場表の直後に置き、直前の表が生む「自分はいくら？」をそのまま拾う文言にする。
        'headline' => '自分の条件だといくら？',
        'sub' => '上の相場はあくまで平均です。年齢・等級・補償内容を入れると、実際の金額が複数社まとめて出ます。',
        'cta_label' => '条件を入れて比較する（無料）',
    ],

    // 自賠責の料金（本土・現行）。排気量3カテゴリ×契約期間（円）。
    // 小型二輪(250cc超)は車検連動のため代表月数のみ（25/37ヶ月等の車検連動月は表示側で注記）。
    'jibaiseki' => [
        'gentsuki' => [
            'label' => '原付（125cc以下）',
            'terms' => [12 => 6910, 24 => 8560, 36 => 10170, 48 => 11760, 60 => 13310],
        ],
        'kei_nirin' => [
            'label' => '軽二輪（125cc超〜250cc）',
            'terms' => [12 => 7100, 24 => 8920, 36 => 10710, 48 => 12470, 60 => 14200],
        ],
        'kogata_nirin' => [
            'label' => '小型二輪（250cc超・車検あり）',
            'terms' => [12 => 7010, 24 => 8760, 36 => 10490],
        ],
    ],

    // 区分（ブラケット）= 軽自動車税(種別割/年額) ＋ 自賠責カテゴリ ＋ ファミバイ特約可否 ＋ 車検有無。
    // 判定は App\Support\InsuranceClassifier::bracketForModel()（排気量＋新基準原付config）。
    'brackets' => [
        'gentsuki_1' => [
            'label' => '原付一種（〜50cc）',
            'tax' => 2000, 'jibaiseki' => 'gentsuki', 'family_tokuyaku' => true, 'shaken' => false,
        ],
        'gentsuki_2_90' => [
            'label' => '原付二種（51〜90cc）',
            'tax' => 2000, 'jibaiseki' => 'gentsuki', 'family_tokuyaku' => true, 'shaken' => false,
        ],
        'gentsuki_2_125' => [
            'label' => '原付二種（91〜125cc）',
            'tax' => 2400, 'jibaiseki' => 'gentsuki', 'family_tokuyaku' => true, 'shaken' => false,
        ],
        'kei_nirin' => [
            'label' => '軽二輪（126〜250cc）',
            'tax' => 3600, 'jibaiseki' => 'kei_nirin', 'family_tokuyaku' => false, 'shaken' => false,
        ],
        'kogata_nirin' => [
            'label' => '小型二輪（250cc超）',
            'tax' => 6000, 'jibaiseki' => 'kogata_nirin', 'family_tokuyaku' => false, 'shaken' => true,
        ],
        // 新基準原付: 125cc以下だが最高出力を制御し「原付一種扱い」。税・特約は原付一種と同じ。
        'shinkijun' => [
            'label' => '新基準原付（原付一種扱い）',
            'tax' => 2000, 'jibaiseki' => 'gentsuki', 'family_tokuyaku' => true, 'shaken' => false,
            'note' => '2025年の新基準原付。総排気量は125cc以下ですが最高出力を4kW以下に制御し、原付一種として扱われます（税・ファミリーバイク特約は原付一種と同じ）。',
        ],
    ],

    // 任意保険料の相場（年間・円）。★固定額ではなく「一括見積もり利用者データの平均」＝出典と平均であることを必ず明示。
    // 出典: インズウェブのバイク保険一括見積もり利用者データ（2025年4月〜2026年3月／2026年8月確認）。
    // ★注意: 数値は出典からの転記のみ（補完・推定・再計算はしない）。
    // ★§4-1: 20等級（rank20）は統計が成立する30代以上のみ掲載。若年（20歳以下・21〜25歳・26〜29歳）の20等級は
    //   サンプル極小で逆転が起きるため null（掲載しない）。6等級（rank6）は全年齢だが、20歳以下・21〜25歳は
    //   low_sample=true（サンプル少の注記を添える）。
    'voluntary_market' => [
        'source' => [
            'name' => 'インズウェブ「バイク任意保険料の相場」',
            'url' => 'https://bike.insweb.co.jp/hokenryo-ikura.html',
            'period' => '2025年4月〜2026年3月の一括見積もり利用者データ',
            'verified' => '2026-08',
        ],
        // 表の下に置く1行（相場の幅を体感させる）。
        'spread_note' => '同じ250cc超でも、30代・20等級なら年間約1.5万円、20代後半・新規6等級なら約4万円。条件次第で3倍近い差が出ます。',
        'categories' => [
            [
                'label' => '125cc以下',
                'rows' => [
                    ['age' => '20歳以下', 'rank6' => 81092, 'rank20' => null, 'low_sample' => true],
                    ['age' => '21〜25歳', 'rank6' => 41869, 'rank20' => null, 'low_sample' => true],
                    ['age' => '26〜29歳', 'rank6' => 34303, 'rank20' => null, 'low_sample' => false],
                    ['age' => '30代', 'rank6' => 33473, 'rank20' => 11656, 'low_sample' => false],
                    ['age' => '40代', 'rank6' => 32564, 'rank20' => 11583, 'low_sample' => false],
                    ['age' => '50代', 'rank6' => 33269, 'rank20' => 12412, 'low_sample' => false],
                    ['age' => '60代', 'rank6' => 31746, 'rank20' => 11566, 'low_sample' => false],
                    ['age' => '70歳以上', 'rank6' => 30544, 'rank20' => 10563, 'low_sample' => false],
                ],
            ],
            [
                'label' => '125cc超〜250cc以下',
                'rows' => [
                    ['age' => '20歳以下', 'rank6' => 131103, 'rank20' => null, 'low_sample' => true],
                    ['age' => '21〜25歳', 'rank6' => 59039, 'rank20' => null, 'low_sample' => true],
                    ['age' => '26〜29歳', 'rank6' => 40854, 'rank20' => null, 'low_sample' => false],
                    ['age' => '30代', 'rank6' => 33236, 'rank20' => 19893, 'low_sample' => false],
                    ['age' => '40代', 'rank6' => 30113, 'rank20' => 16577, 'low_sample' => false],
                    ['age' => '50代', 'rank6' => 29678, 'rank20' => 15891, 'low_sample' => false],
                    ['age' => '60代', 'rank6' => 30542, 'rank20' => 16986, 'low_sample' => false],
                    ['age' => '70歳以上', 'rank6' => 35415, 'rank20' => 16451, 'low_sample' => false],
                ],
            ],
            [
                'label' => '250cc超',
                'rows' => [
                    ['age' => '20歳以下', 'rank6' => 140629, 'rank20' => null, 'low_sample' => true],
                    ['age' => '21〜25歳', 'rank6' => 62366, 'rank20' => null, 'low_sample' => true],
                    ['age' => '26〜29歳', 'rank6' => 40718, 'rank20' => null, 'low_sample' => false],
                    ['age' => '30代', 'rank6' => 33441, 'rank20' => 14680, 'low_sample' => false],
                    ['age' => '40代', 'rank6' => 30059, 'rank20' => 15993, 'low_sample' => false],
                    ['age' => '50代', 'rank6' => 32361, 'rank20' => 15249, 'low_sample' => false],
                    ['age' => '60代', 'rank6' => 31788, 'rank20' => 17941, 'low_sample' => false],
                    ['age' => '70歳以上', 'rank6' => 34158, 'rank20' => 22589, 'low_sample' => false],
                ],
            ],
        ],
    ],

    // ファミリーバイク特約の金額目安（125cc以下）。出典: インズウェブ「ファミリーバイク特約の保険料は？」。
    // ★平均/目安であることを明示。金額は転記のみ。
    'family_tokuyaku_cost' => [
        'source' => [
            'name' => 'インズウェブ「ファミリーバイク特約の保険料は？」',
            'url' => 'https://bike.insweb.co.jp/family-bike.html',
        ],
        'rows' => [
            ['label' => 'ファミリーバイク特約・自損事故型', 'amount' => '約10,000円'],
            ['label' => 'ファミリーバイク特約・人身傷害型', 'amount' => '約30,000円'],
            ['label' => 'バイク保険単体（20代）', 'amount' => '約40,000円'],
        ],
        'note' => '対象は125cc以下（原付一種・原付二種・2025年の新基準原付を含む）。等級に影響せず、複数台でも定額。車両保険は対象外。',
    ],
];
