<?php

use App\Models\RentalBikeShop;
use App\Models\RentalGarage;
use App\Models\Station;
use Illuminate\Support\Facades\Schema;

/**
 * レンタルバイク店舗の公開ページ（第2弾）。
 *
 * ★外部アクセスはしない。★画像・紹介文・在庫は一切扱わない方針の回帰テスト。
 * 電話が無い店舗で電話欄が出ないこと・周辺0件のブロックが消えること・評価語が入らないことを担保する。
 */

/** テスト用の店舗を1件作る（dedup_key はモデルのキー生成に合わせる）。 */
function makeRentalBikeShop(array $attrs): RentalBikeShop
{
    $slug = $attrs['company_slug'] ?? 'bikecenter';
    $name = $attrs['name'] ?? '店舗';
    $address = $attrs['address'] ?? '住所';

    return RentalBikeShop::create(array_merge([
        'company' => 'レンタルバイクセンター',
        'company_slug' => $slug,
        'external_id' => null,
        'name' => $name,
        'postal_code' => null,
        'address' => $address,
        'tel' => null,
        'opening_hours' => null,
        'official_url' => 'https://example.test/shop',
        'is_active' => true,
        'fetched_at' => now(),
        'dedup_key' => RentalBikeShop::makeDedupKey($slug, null, $name, $address),
    ], $attrs));
}

beforeEach(function () {
    // 東京の店舗（電話なし＝Motobase 相当）。駅・ガレージが近くにある。
    $this->tokyoNoTel = makeRentalBikeShop([
        'company' => 'Motobase',
        'company_slug' => 'motobase',
        'name' => 'モトベース千代田',
        'address' => '東京都千代田区丸の内1-1',
        'prefecture' => '東京都',
        'city' => '千代田区',
        'tel' => null,
        'official_url' => 'https://motobase.jp/rental-bike/tokyo/chiyoda/marunouchi',
        'latitude' => 35.6812,
        'longitude' => 139.7671,
    ]);

    // 東京の店舗（電話あり）。
    makeRentalBikeShop([
        'name' => 'バイクセンター大田',
        'address' => '東京都大田区蒲田1-1',
        'prefecture' => '東京都',
        'city' => '大田区',
        'tel' => '03-1234-5678',
        'latitude' => 35.5626,
        'longitude' => 139.7161,
    ]);

    // 神奈川・千葉・埼玉に1件ずつ（都道府県ページ4枚の検証用）。千葉は周辺0件（遠隔座標）。
    makeRentalBikeShop([
        'name' => 'バイクセンター横浜',
        'address' => '神奈川県横浜市緑区中山1-1',
        'prefecture' => '神奈川県',
        'city' => '横浜市緑区',
        'tel' => '045-000-0000',
        'latitude' => 35.5100,
        'longitude' => 139.5400,
    ]);
    $this->chibaIsolated = makeRentalBikeShop([
        'name' => 'バイクセンター千葉',
        'address' => '千葉県千葉市中央区1-1',
        'prefecture' => '千葉県',
        'city' => '千葉市中央区',
        'tel' => '043-000-0000',
        'latitude' => 35.6070,
        'longitude' => 140.1060,
    ]);
    makeRentalBikeShop([
        'name' => 'バイクセンターさいたま',
        'address' => '埼玉県さいたま市大宮区1-1',
        'prefecture' => '埼玉県',
        'city' => 'さいたま市大宮区',
        'tel' => '048-000-0000',
        'latitude' => 35.9060,
        'longitude' => 139.6230,
    ]);

    // 東京店の近く（3km以内）に駅とレンタルガレージを置く（周辺ブロック・自動文の検証用）。
    Station::create([
        'name' => '東京',
        'slug' => 'tokyo-test',
        'prefecture' => '東京都',
        'city' => '千代田区',
        'latitude' => 35.6812,
        'longitude' => 139.7660,
    ]);
    $this->garage = RentalGarage::create([
        'name' => 'テストガレージ丸の内',
        'prefecture' => '東京都',
        'city' => '千代田区',
        'address' => '東京都千代田区丸の内1-2',
        'latitude' => 35.6815,
        'longitude' => 139.7665,
        'is_active' => true,
    ]);
});

it('renders the national index without a total count', function () {
    $res = $this->get('/rental-bikes');

    $res->assertOk()
        ->assertSee('レンタルバイク店舗一覧')
        ->assertSee('モトベース千代田')
        ->assertSee('東京都')
        ->assertSee('神奈川県');

    // ★総数（「全◯店舗」）を出さないこと。
    $res->assertDontSee('全5店舗');
    $res->assertDontSee('5店舗');
});

it('hides the phone field for a shop without tel and emphasizes the official site', function () {
    $res = $this->get('/rental-bikes/'.$this->tokyoNoTel->id);

    $res->assertOk()
        ->assertSee('モトベース千代田')
        ->assertSee('東京都千代田区丸の内1-1')
        ->assertSee('公式サイトで見る')
        ->assertSee('予約・お問い合わせは公式サイトから');

    // ★「情報なし」と出さない（項目ごと非表示）。
    $res->assertDontSee('情報なし');

    // ★公式リンクに rel="nofollow" は付けない。
    $res->assertSee('rel="noopener"', false);
    $res->assertDontSee('nofollow');
});

it('shows the phone field and no official-site nudge when tel exists', function () {
    $res = $this->get('/rental-bikes/'.$this->chibaIsolated->id);

    $res->assertOk()
        ->assertSee('043-000-0000')
        ->assertDontSee('予約・お問い合わせは公式サイトから');
});

it('shows nearby station and garage blocks with factual auto text', function () {
    $res = $this->get('/rental-bikes/'.$this->tokyoNoTel->id);

    $res->assertOk()
        ->assertSee('近くの駅')
        ->assertSee('近くのレンタルガレージ')
        ->assertSee('テストガレージ丸の内')
        // 自動文（事実のみ）。
        ->assertSee('最寄りの東京駅から約')
        ->assertSee('周辺にレンタルガレージが');
});

it('hides nearby blocks entirely when nothing is within range', function () {
    // 千葉店の周辺（3km以内）には駅もガレージも無い → ブロックごと非表示。
    $res = $this->get('/rental-bikes/'.$this->chibaIsolated->id);

    $res->assertOk()
        ->assertDontSee('近くのレンタルガレージ')
        ->assertDontSee('近くの駅');
});

it('never outputs evaluation words in the auto text', function () {
    $res = $this->get('/rental-bikes/'.$this->tokyoNoTel->id);
    $res->assertOk();

    // 自動生成の説明文だけを取り出して検証する（このクラスは show.blade で自動文にだけ使う）。
    // ページ全体だと nav/フッターの「人気」等の chrome が誤検出になるため、対象を自動文に絞る。
    preg_match('/<p class="text-sm text-gray-700 leading-relaxed mb-4">(.*?)<\/p>/su', $res->getContent(), $m);
    $autoText = $m[1] ?? '';

    expect($autoText)->not->toBe('');
    foreach (['おすすめ', '安心', '便利', '人気', '快適', '最高'] as $word) {
        expect($autoText)->not->toContain($word);
    }
});

it('has no image-related columns or attributes', function () {
    // テーブルに画像カラムが無い。
    $columns = Schema::getColumnListing('rental_bike_shops');
    foreach (['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'] as $banned) {
        expect($columns)->not->toContain($banned);
    }

    // 詳細ページに店舗画像の出力が無い（店舗名の <img> 等を持たない）。
    $res = $this->get('/rental-bikes/'.$this->tokyoNoTel->id);
    $res->assertOk()->assertDontSee('image_url');
});

it('renders all four prefecture pages', function () {
    foreach (['東京都' => 'モトベース千代田', '神奈川県' => 'バイクセンター横浜', '千葉県' => 'バイクセンター千葉', '埼玉県' => 'バイクセンターさいたま'] as $pref => $shopName) {
        $this->get('/rental-bikes/'.$pref)
            ->assertOk()
            ->assertSee($pref)
            ->assertSee($shopName);
    }
});

it('404s a prefecture with no shops', function () {
    $this->get('/rental-bikes/'.'沖縄県')->assertNotFound();
});

it('adds a reverse link from the rental garage detail to nearby rental bikes', function () {
    $res = $this->get('/rental-garages/'.$this->garage->id);

    $res->assertOk()
        ->assertSee('近くのレンタルバイク')
        ->assertSee('モトベース千代田');
});
