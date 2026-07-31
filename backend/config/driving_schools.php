<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 確認頻度の層（tier）
    |--------------------------------------------------------------------------
    |
    | 教習所データの再確認頻度を県単位で分ける。
    |  - focus（重点層） : 料金3軸まで持ち、四半期ごとに確認する県。
    |  - nation（全国層）: 二輪可否＋公式リンクを、年1回確認する県。
    |
    | 層は県ごとに決まるものなので、DBの列ではなく prefecture_slug の設定値で持つ
    | （列にすると県が層を移るたびに全行 UPDATE が要る）。
    | focus_prefectures に列挙した県だけが focus、それ以外は全て nation。
    |
    */

    'focus_prefectures' => [
        'tokyo',
        'kanagawa',
    ],

    'default_tier' => 'nation',

    /*
    |--------------------------------------------------------------------------
    | 県協会リンク（個別掲載を行っていない都道府県）
    |--------------------------------------------------------------------------
    |
    | 教習所を個別に掲載していない県について、一覧ページ（/license/schools）下部
    | から各県の指定自動車教習所協会（一次ソース）の公式サイトへ外部リンクする。
    | 個別県ページ（show）や sitemap は生やさない（薄ページを作らない方針）。
    |
    | 出典＝全指連 zensiren.or.jp/nwide-info/「47都道府県協会一覧」（2026-07-29 確認）。
    | ただし鳥取・大分は協会サイトを確認できず、鳥取県公式／大分県警察の指定教習所一覧を
    | 一次源として採用（name も「協会」とは呼ばず県公式／県警の一覧として表記）。
    | name は各協会サイトの title 由来の正式名称（学校/教習所・財団・府 の揺れをそのまま）。
    | url は https 実測 200 を採用。https が提供されない5県（栃木・群馬・福井・滋賀・岐阜）
    | のみ http 実測 200 を採用（https 強制は 403/TLS/接続拒否/404、岐阜は共用証明書
    | *.bizmw.com が www.gishikyo.jp を含まず ERR_CERT_COMMON_NAME_INVALID になるため）。
    | 並びは北から南。保留4県（石川=500・奈良・和歌山・佐賀＝公式の一覧ページを確認できず）
    | は URL 未確認のため入れない（NOTES で要URL確認）。
    |
    */

    'association_links' => [
        'aomori' => ['name' => '一般社団法人青森県指定自動車教習所協会', 'url' => 'https://www.aoshikyo.jp/'],
        'iwate' => ['name' => '一般社団法人 岩手県指定自動車教習所協会', 'url' => 'https://iwate-siteikyou.sakura.ne.jp/'],
        'miyagi' => ['name' => '一般社団法人 宮城県指定自動車教習所協会', 'url' => 'https://miyazikyo.jp/'],
        'akita' => ['name' => '一般社団法人 秋田県指定自動車教習所協会', 'url' => 'https://akita-adsa.com/'],
        'yamagata' => ['name' => '一般社団法人 山形県指定自動車教習所協会', 'url' => 'https://www.yamagata-shiteikyo.or.jp/'],
        'fukushima' => ['name' => '一般社団法人 福島県指定自動車教習所協会', 'url' => 'https://www.fukushima-adsa.org/'],
        'ibaraki' => ['name' => '一般社団法人 茨城県指定自動車教習所協会', 'url' => 'https://iadsa.or.jp/'],
        'tochigi' => ['name' => '一般社団法人 栃木県指定自動車教習所協会', 'url' => 'http://www.totikyou.jp/'],
        'gunma' => ['name' => '一般社団法人 群馬県指定自動車教習所協会', 'url' => 'http://gunma-adsa.com/'],
        'yamanashi' => ['name' => '山梨県指定自動車教習所協会', 'url' => 'https://y-shiteikyo.com/'],
        'nagano' => ['name' => '一般社団法人 長野県指定自動車教習所協会', 'url' => 'https://e-office.gr.jp/kyosyujyo/'],
        'niigata' => ['name' => '一般社団法人 新潟県指定自動車教習所協会', 'url' => 'https://www.niigatashiteikyo.or.jp/'],
        'toyama' => ['name' => '富山県指定自動車教習所協会', 'url' => 'https://www.tomijikyo.or.jp/'],
        'fukui' => ['name' => '一般社団法人 福井県指定自動車教習所協会', 'url' => 'http://www.fukuiadsa.or.jp/'],
        'gifu' => ['name' => '岐阜県指定自動車教習所協会', 'url' => 'http://www.gishikyo.jp/'],
        'mie' => ['name' => '一般社団法人 三重県指定自動車教習所協会', 'url' => 'https://miejikyo.com/'],
        'shiga' => ['name' => '滋賀県指定自動車教習所協会', 'url' => 'http://www.shiga-shiteikyo.org/'],
        'kyoto' => ['name' => '一般社団法人京都府指定自動車教習所協会', 'url' => 'https://www.kyoto-shiteikyo.or.jp/'],
        'tottori' => ['name' => '鳥取県 指定自動車教習所一覧（鳥取県公式）', 'url' => 'https://www.pref.tottori.lg.jp/320880.htm'],
        'shimane' => ['name' => '一般社団法人 島根県指定自動車教習所協会', 'url' => 'https://shimajikyo.jp/'],
        'okayama' => ['name' => '岡山県指定自動車教習所協会', 'url' => 'https://www.okajikyo.or.jp/'],
        'hiroshima' => ['name' => '広島県指定自動車学校協会', 'url' => 'https://www.hirojikyo.info/'],
        'yamaguchi' => ['name' => '一般社団法人山口県指定自動車学校協会', 'url' => 'https://yamajikyo.jp/'],
        'tokushima' => ['name' => '徳島県指定自動車教習所協会', 'url' => 'https://tokushikyo.sakura.ne.jp/'],
        'kagawa' => ['name' => '一般社団法人 香川県指定自動車学校協会', 'url' => 'https://kadsa.or.jp/'],
        'ehime' => ['name' => '一般社団法人 愛媛県指定自動車教習所協会', 'url' => 'https://www.eadsa.or.jp/'],
        'kochi' => ['name' => '一般社団法人 高知県指定自動車学校協会', 'url' => 'https://www.kochi-shiteikyo.or.jp/'],
        'nagasaki' => ['name' => '一般社団法人 長崎県指定自動車学校協会', 'url' => 'https://www.nadsa.jp/'],
        'kumamoto' => ['name' => '一般財団法人 熊本県指定自動車教習所協会', 'url' => 'https://www.kumakyo.or.jp/'],
        'oita' => ['name' => '大分県 指定自動車教習所一覧（大分県警察）', 'url' => 'https://www.pref.oita.jp/site/keisatu/mennsyu.html'],
        'miyazaki' => ['name' => '一般社団法人宮崎県指定自動車学校協会', 'url' => 'https://miyashiji.org/'],
        'kagoshima' => ['name' => '鹿児島県指定自動車教習所協会', 'url' => 'https://www.ka-shiteikyo.school-info.jp/'],
        'okinawa' => ['name' => '一般社団法人 沖縄県指定自動車学校協会', 'url' => 'https://www.okizikyo.or.jp/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | アフィリエイト（教習所ページ /license/schools/{pref} の合宿導線）
    |--------------------------------------------------------------------------
    |
    | 通いの教習所一覧の下に、合宿免許の申込先へ送客する枠を出す。★承認後に発行URLを
    | env に入れる。url 未設定の間は枠自体を非表示（偽ボタンを置かない）。
    | insurance.affiliate / theft.affiliate と同型。二輪の合宿可否・料金・日程は申込先で
    | 確認させる文言にする（合宿で二輪が取れると断定しない）。
    |
    */

    'affiliate' => [
        'url' => env('SCHOOL_AFFILIATE_URL', ''),
        // 任意: インプレッション計測URL。設定時のみ CTA表示で 1x1 img を出す（未設定は出さない）。
        'imp_url' => env('SCHOOL_AFFILIATE_IMP_URL', ''),
        'provider' => env('SCHOOL_AFFILIATE_PROVIDER', ''),
        // リンクのアンカーテキスト。未設定ならリンク自体を出さない。
        'label' => env('SCHOOL_AFFILIATE_LABEL', ''),
    ],
];
