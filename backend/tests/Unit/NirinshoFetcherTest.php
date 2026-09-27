<?php

use App\Services\Parking\AddressParser;
use App\Support\RentalBike\Fetchers\NirinshoFetcher;

/**
 * 二輪処グループ Fetcher の単体テスト（★外部アクセスはしない）。
 * ★全店が1ページ /store/ に載る＝詳細ページ取得なし。
 * ★電話は「レンタル/予約」の番号を優先（修理・販売の番号を拾わない）＋ハイフン正規化。
 * ★住所は都道府県が無くても市区町村→都道府県を AddressParser が解決する。
 * ★画像キーを返さない。
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    AddressParser::setMunicipalitiesForTesting([
        '大阪市阿倍野区', '摂津市', '東大阪市',
    ]);
});

afterEach(function () {
    AddressParser::flushMunicipalityCache();
});

// 一覧断片（実構造）。m-store 内に anc(id) + h2 + table。最後は h2 の無い＝店舗でないブロック。
$listHtml = <<<'HTML'
<article class="m-store">
<div class="anc" id="store01"></div><section><div class="store_content">
  <div class="thumb"><img src="x.jpg"></div>
  <div class="text"><h2>二輪処Saga屋 阿倍野店</h2>
    <table>
      <tr><th>住所</th><td>〒545-0011<br>大阪市阿倍野区昭和町3-8-1</td></tr>
      <tr><th>電話番号</th><td>070-5042-9484 レンタル 06-6606-8403 修理・販売</td></tr>
      <tr><th>営業時間</th><td>AM10:00～PM19:00</td></tr>
      <tr><th>定休日</th><td>毎週火曜日</td></tr>
    </table>
  </div></div></section>
<div class="anc" id="store02"></div><section><div class="store_content">
  <div class="text"><h2>二輪処エスペラント 摂津店</h2>
    <table>
      <tr><th>住所</th><td>〒566-0042<br>大阪府摂津市東別府3-7-12</td></tr>
      <tr><th>電話番号</th><td>0668765433</td></tr>
      <tr><th>営業時間</th><td>AM10:00～PM19:00</td></tr>
    </table>
  </div></div></section>
<div class="anc" id="store07"></div><section><div class="store_content">
  <div class="text"><h2>二輪処 東大阪 鴻池新田店</h2>
    <table>
      <tr><th>住所</th><td>〒578-0974<br>東大阪市鴻池元町7番14号</td></tr>
      <tr><th>電話番号</th><td>090-7552-8403</td></tr>
      <tr><th>営業時間</th><td>AM10:00〜PM19:00</td></tr>
    </table>
  </div></div></section>
<div class="anc" id="store99"></div><section><div class="text"><p>準備中</p></div></section>
</article>
HTML;

it('extracts stores by anchor+h2, skipping blocks without an h2', function () use ($listHtml) {
    $shops = (new NirinshoFetcher)->extractShops($listHtml);

    expect($shops)->toHaveCount(3)
        ->and(array_column($shops, 'id'))->toBe(['store01', 'store02', 'store07'])
        ->and($shops[0]['name'])->toBe('二輪処Saga屋 阿倍野店')
        ->and($shops[0]['url'])->toBe('https://www.bike-rental.jp/store/#store01');
});

it('backfills 都道府県 for a city with no 都道府県 prefix via the reverse lookup (東大阪市 → 大阪府)', function () use ($listHtml) {
    AddressParser::setCityPrefecturesForTesting(['東大阪市' => '大阪府']);

    $shops = (new NirinshoFetcher)->extractShops($listHtml);
    $record = (new NirinshoFetcher)->buildRecord($shops[2]); // 東大阪 鴻池新田店（住所に都道府県なし）

    expect($record)->not->toBeNull()
        ->and($record['city'])->toBe('東大阪市')
        ->and($record['prefecture'])->toBe('大阪府') // ★逆引きで補完
        ->and($record['tel'])->toBe('090-7552-8403');
});

it('builds a record and picks the レンタル phone (not 修理・販売), resolving city without 都道府県', function () use ($listHtml) {
    $shops = (new NirinshoFetcher)->extractShops($listHtml);
    $record = (new NirinshoFetcher)->buildRecord($shops[0]);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('二輪処Saga屋 阿倍野店')
        ->and($record['postal_code'])->toBe('545-0011')
        ->and($record['address'])->toBe('大阪市阿倍野区昭和町3-8-1')
        ->and($record['city'])->toBe('大阪市阿倍野区')
        ->and($record['tel'])->toBe('070-5042-9484')      // ★レンタルの番号
        ->and($record['tel'])->not->toBe('06-6606-8403')  // ★修理・販売は拾わない
        ->and($record['opening_hours'])->toBe('AM10:00～PM19:00')
        ->and($record['external_id'])->toBe('store01')
        ->and($record['official_url'])->toBe('https://www.bike-rental.jp/store/#store01');
});

it('hyphenates a hyphenless landline via the shared formatJpTel (06 → 2-4-4)', function () use ($listHtml) {
    $shops = (new NirinshoFetcher)->extractShops($listHtml);
    $record = (new NirinshoFetcher)->buildRecord($shops[1]);

    expect($record)->not->toBeNull()
        ->and($record['city'])->toBe('摂津市')
        ->and($record['tel'])->toBe('06-6876-5433'); // 0668765433 → 06-6876-5433
});

it('returns null when the address has no resolvable city', function () {
    $shop = ['id' => 'storeX', 'name' => 'テスト', 'address_td' => '〒000-0000<br>昭和町3-8-1', 'tel_td' => '06-1234-5678', 'hours' => '', 'url' => 'u'];
    expect((new NirinshoFetcher)->buildRecord($shop))->toBeNull();
});

it('never returns image-related keys', function () use ($listHtml) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ((new NirinshoFetcher)->extractShops($listHtml) as $shop) {
        $record = (new NirinshoFetcher)->buildRecord($shop);
        if ($record !== null) {
            expect(array_intersect($banned, array_keys($record)))->toBe([]);
        }
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new NirinshoFetcher;

    expect($f->slug())->toBe('nirinsho')
        ->and($f->company())->toBe('二輪処グループ')
        ->and($f->officialUrl())->toBe('https://www.bike-rental.jp/');
});
