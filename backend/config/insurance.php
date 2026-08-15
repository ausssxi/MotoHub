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
        // 事実ベースの文言のみ（誇大表現・保険募集にあたる比較/推奨はしない）
        'headline' => 'バイク保険（任意保険）を一括見積もり',
        'sub' => '複数社の見積もりをまとめて比較（無料）。実際の保険料は条件で変わるため、見積もりで確認できます。',
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
];
