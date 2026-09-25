<?php

use App\Services\Parking\AddressParser;
use App\Support\RentalBike\Fetchers\YamahaFetcher;

/**
 * ヤマハ バイクレンタル Fetcher の単体テスト。
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（★外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★取れない項目（営業時間）は null で残ること。
 * ★住所→都道府県/市区町村は既存 AddressParser に揃うこと。
 * ★取得ヘッダ（Accept-Language: ja）の回帰は tests/Feature 側（要アプリbootstrap）。
 *
 * 一覧の断片は本番HTMLの実構造（2026-09 確認・/jp/region/common/include/shop.html）:
 *   <li><a href="/jp/shop/info/detail/shopcd/S0103"><span>北海道／YSP帯広</span></a></li>
 *   ★span は「都道府県／店名」（全角スラッシュ）。同一 shopcd の重複導線は潰す。shopcd 以外のリンクは店舗ではない。
 *
 * 詳細の断片は本番HTMLの実構造（2026-09 確認・shopcd/S0103, S0102）:
 *   <table class="shopInfo ...">
 *     <tr class="address"><th>住所</th><td>〒NNN-NNNN<br>都道府県＋市区町村＋番地 <a>アクセス</a></td></tr>
 *     <tr><th>電話番号</th><td><b data-tel="...">0xx-xxx-xxxx</b></td></tr>
 *     <tr><th>営業時間</th><td>10:00～18:00</td></tr>
 *   </table>
 * ★住所は shopInfo テーブルから <th> ラベルで <td> を引く。「アクセス」<a> は除去する。
 * ★住所は都道府県込み。元データに「札幌市 西区」のような内部空白が混じるので除去する（政令市の区の解決のため）。
 */

// AddressParser の city 突合を決定的にする（DB非依存）。
uses(Tests\TestCase::class);

beforeEach(function () {
    AddressParser::setMunicipalitiesForTesting([
        '帯広市', '札幌市西区', '横浜市緑区',
    ]);
});

afterEach(function () {
    AddressParser::flushMunicipalityCache(); // 静的キャッシュを他テストへ持ち越さない
});

// 一覧ページ断片（実構造）。都道府県／店名。重複 shopcd・shopcd 以外のリンクは店舗ではない。
$listHtml = <<<'HTML'
<h2 class="section_heading">全国のヤマハ バイクレンタル店舗一覧</h2>
<ul>
  <li><a href="/jp/shop/info/detail/shopcd/S0103"><span>北海道／YSP帯広</span></a></li>
  <li><a href="/jp/shop/info/detail/shopcd/S0102"><span>北海道／YSP札幌西</span></a></li>
  <li><a href="/jp/shop/info/detail/shopcd/S0103"><span>北海道／YSP帯広（重複導線）</span></a></li>
</ul>
<nav><a href="/jp/guide/usage"><span>ご利用案内</span></a></nav>
HTML;

// 店舗詳細（shopInfo テーブル）: 住所・電話・営業時間が揃うケース。★アクセス <a> は住所に拾わない。
$detailFull = <<<'HTML'
<h1>YSP帯広</h1>
<table class="shopInfo nmlTbl mb30">
  <tr class="address"><th>住所</th><td>〒080-0026<br>北海道帯広市西16条南30丁目2-23 <a href="#access" target="_blank">アクセス</a></td></tr>
  <tr><th>電話番号</th><td><b data-action="call" data-tel="0155481417">0155-48-1417</b></td></tr>
  <tr><th>営業時間</th><td>10:00～18:00</td></tr>
  <tr><th>定休日</th><td>毎週月曜・第3火曜</td></tr>
</table>
HTML;

// 店舗詳細: 住所に内部空白（札幌市 西区）＝元データの表記ゆれ。営業時間の th が無い（→ null）。
$detailSpaceNoHours = <<<'HTML'
<table class="shopInfo">
  <tr class="address"><th>住所</th><td>〒063-0052<br>北海道札幌市 西区宮の沢二条1丁目10-21 <a href="#access">アクセス</a></td></tr>
  <tr><th>電話番号</th><td><b data-tel="0116626526">011-662-6526</b></td></tr>
</table>
HTML;

it('extracts shopcd + name + prefecture from the list (都道府県／店名), dropping dupes and non-shop links', function () use ($listHtml) {
    $stores = (new YamahaFetcher)->extractStores($listHtml);

    expect($stores)->toHaveCount(2);

    expect($stores[0]['id'])->toBe('S0103')
        ->and($stores[0]['url'])->toBe('https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S0103')
        ->and($stores[0]['name'])->toBe('YSP帯広')     // ★／の後ろが店名
        ->and($stores[0]['prefecture'])->toBe('北海道'); // ★／の前が都道府県

    expect($stores[1]['id'])->toBe('S0102')
        ->and($stores[1]['name'])->toBe('YSP札幌西')
        ->and($stores[1]['prefecture'])->toBe('北海道');

    // 重複 shopcd（S0103）は1件だけ・「ご利用案内」は shopcd リンクではないので店舗にしない。
    $ids = array_column($stores, 'id');
    expect($ids)->toBe(['S0103', 'S0102']);
});

it('keeps the whole label as the name when there is no ／ separator', function () {
    $list = '<li><a href="/jp/shop/info/detail/shopcd/S9999"><span>ヤマハ バイクレンタル特設</span></a></li>';

    $stores = (new YamahaFetcher)->extractStores($list);

    expect($stores)->toHaveCount(1)
        ->and($stores[0]['name'])->toBe('ヤマハ バイクレンタル特設')
        ->and($stores[0]['prefecture'])->toBeNull();
});

it('builds a record from the list (name/prefecture) and detail shopInfo table (address/postal/tel/hours)', function () use ($detailFull) {
    $store = ['id' => 'S0103', 'url' => 'https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S0103', 'name' => 'YSP帯広', 'prefecture' => '北海道'];
    $record = (new YamahaFetcher)->buildRecord($store, $detailFull);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('YSP帯広')
        ->and($record['postal_code'])->toBe('080-0026')
        ->and($record['address'])->toBe('北海道帯広市西16条南30丁目2-23')
        ->and($record['prefecture'])->toBe('北海道')
        ->and($record['city'])->toBe('帯広市')
        ->and($record['tel'])->toBe('0155-48-1417')          // ★<b data-tel> のタグは落ちる
        ->and($record['opening_hours'])->toBe('10:00～18:00')
        ->and($record['external_id'])->toBe('S0103')
        ->and($record['official_url'])->toBe('https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S0103');

    // ★「アクセス」リンク文言と data-tel の生数字が値に混入しない。
    expect($record['address'])->not->toContain('アクセス')
        ->and($record['tel'])->not->toContain('0155481417');
});

// ★回帰: 住所内の空白（政令市名と区名の間）を除去して区まで解決する／営業時間 th が無ければ null。
it('strips internal address whitespace (札幌市 西区 → 札幌市西区) and leaves hours null when absent', function () use ($detailSpaceNoHours) {
    $store = ['id' => 'S0102', 'url' => 'https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S0102', 'name' => 'YSP札幌西', 'prefecture' => '北海道'];
    $record = (new YamahaFetcher)->buildRecord($store, $detailSpaceNoHours);

    expect($record)->not->toBeNull()
        ->and($record['postal_code'])->toBe('063-0052')
        ->and($record['address'])->toBe('北海道札幌市西区宮の沢二条1丁目10-21') // ★空白除去
        ->and($record['city'])->toBe('札幌市西区')                              // ★区まで解決
        ->and($record['tel'])->toBe('011-662-6526')
        ->and($record['opening_hours'])->toBeNull();                          // ★無理に埋めない
});

it('returns null when the shopInfo table is missing or the city cannot be resolved', function () {
    $store = ['id' => 'S0000', 'url' => 'https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S0000', 'name' => 'テスト店', 'prefecture' => '北海道'];

    // shopInfo テーブルそのものが無い → null。
    expect((new YamahaFetcher)->buildRecord($store, '<main><p>北海道帯広市西16条南30丁目2-23</p></main>'))->toBeNull();

    // テーブルはあるが住所から市区町村が取れない → null。
    $noCity = '<table class="shopInfo"><tr><th>住所</th><td>〒000-0000<br>西16条南30丁目2-23</td></tr></table>';
    expect((new YamahaFetcher)->buildRecord($store, $noCity))->toBeNull();
});

it('never returns image-related keys', function () use ($detailFull, $detailSpaceNoHours) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ([$detailFull, $detailSpaceNoHours] as $i => $html) {
        $record = (new YamahaFetcher)->buildRecord(
            ['id' => "S000{$i}", 'url' => "https://bike-rental.yamaha-motor.co.jp/jp/shop/info/detail/shopcd/S000{$i}", 'name' => '店', 'prefecture' => '北海道'],
            $html
        );
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new YamahaFetcher;

    expect($f->slug())->toBe('yamaha')
        ->and($f->company())->toBe('ヤマハ バイクレンタル')
        ->and($f->officialUrl())->toBe('https://bike-rental.yamaha-motor.co.jp/');
});
