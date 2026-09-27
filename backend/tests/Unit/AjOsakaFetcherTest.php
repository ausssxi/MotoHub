<?php

use App\Services\Parking\AddressParser;
use App\Support\RentalBike\Fetchers\AjOsakaFetcher;

/**
 * 大阪オートバイ事業協同組合 Fetcher の単体テスト（★外部アクセスはしない）。
 * ★全店が1ページ /tenpo.php に載る＝詳細ページ取得なし。
 * ★画像キーを返さない。★営業時間は一覧に無いので常に null。
 * ★住所は 〒 の <li> 直後の <li> を取る（都道府県の有無に依存しない）。
 * ★元データの重複バグ「大阪府豊中市大阪府豊中市…」「東大阪市東大阪市…」を畳む。
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    AddressParser::setMunicipalitiesForTesting([
        '豊中市', '東大阪市', '伊丹市', '枚方市',
    ]);
});

afterEach(function () {
    AddressParser::flushMunicipalityCache();
});

// 一覧断片（実構造）。boxs=店舗ブロック。名前=div.sn、事実情報=#jyouhou_box の li。
// s_A は重複導線・末尾に「店舗でないブロック（shop_bike リンク無し）」を混ぜる。
$listHtml = <<<'HTML'
<div class='boxs'><div class='sn'>Favorite Factory</div>
<a href='shop_bike.php?shop=s_A'><img src='x.jpg'></a>
<ul><div id='jyouhou_box'>
  <li class='b1'>〒560-0041</li>
  <li>大阪府豊中市大阪府豊中市清風荘1-15-6</li>
  <li class='b1'>電話：06-4866-6000</li>
  <li>定休日：火曜日</li>
</div></ul></div>
<div class='boxs'><div class='sn'>エナジーモータースタイル伊丹本店</div>
<a href='shop_bike.php?shop=s_B'><img src='x.jpg'></a>
<ul><div id='jyouhou_box'>
  <li class='b1'>〒664-0832</li>
  <li>伊丹市下河原
1-2-23</li>
  <li class='b1'>電話：0728295099</li>
</div></ul></div>
<div class='boxs'><div class='sn'>エヌケーファクトリー</div>
<a href='shop_bike.php?shop=s_C'><img src='x.jpg'></a>
<ul><div id='jyouhou_box'>
  <li class='b1'>〒579-8014</li>
  <li>大阪府東大阪市東大阪市中石切町7-4-68</li>
  <li class='b1'>電話：0120941389</li>
</div></ul></div>
<div class='boxs'><div class='sn'>Favorite Factory（重複導線）</div>
<a href='shop_bike.php?shop=s_A'><img src='x.jpg'></a></div>
<div class='boxs'><div class='sn'>店舗でない案内</div><p>お知らせ</p></div>
HTML;

it('extracts member shops (name/id/postal/address/tel), dropping dupes and non-shop blocks', function () use ($listHtml) {
    $shops = (new AjOsakaFetcher)->extractShops($listHtml);

    expect($shops)->toHaveCount(3)
        ->and(array_column($shops, 'id'))->toBe(['s_A', 's_B', 's_C']);

    expect($shops[0]['name'])->toBe('Favorite Factory')
        ->and($shops[0]['postal'])->toBe('560-0041')
        ->and($shops[0]['tel'])->toBe('06-4866-6000')
        ->and($shops[0]['url'])->toBe('https://aj-rentalbike.com/shop_bike.php?shop=s_A');
});

it('builds a record and collapses the duplicated 都道府県+市 address bug', function () use ($listHtml) {
    $shops = (new AjOsakaFetcher)->extractShops($listHtml);
    $record = (new AjOsakaFetcher)->buildRecord($shops[0]);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('Favorite Factory')
        ->and($record['postal_code'])->toBe('560-0041')
        ->and($record['address'])->toBe('大阪府豊中市清風荘1-15-6') // ★「大阪府豊中市」重複を畳む
        ->and($record['prefecture'])->toBe('大阪府')
        ->and($record['city'])->toBe('豊中市')
        ->and($record['tel'])->toBe('06-4866-6000')
        ->and($record['opening_hours'])->toBeNull()               // ★一覧に営業時間は無い
        ->and($record['external_id'])->toBe('s_A')
        ->and($record['official_url'])->toBe('https://aj-rentalbike.com/shop_bike.php?shop=s_A');
});

it('collapses a duplicated city token (東大阪市東大阪市 → 東大阪市)', function () use ($listHtml) {
    $shops = (new AjOsakaFetcher)->extractShops($listHtml);
    $record = (new AjOsakaFetcher)->buildRecord($shops[2]);

    expect($record)->not->toBeNull()
        ->and($record['address'])->toBe('大阪府東大阪市中石切町7-4-68')
        ->and($record['city'])->toBe('東大阪市')
        ->and($record['tel'])->toBe('0120-941-389'); // ★フリーダイヤルもハイフン付きに
});

it('handles an address with no 都道府県 prefix and a newline (伊丹市…)', function () use ($listHtml) {
    $shops = (new AjOsakaFetcher)->extractShops($listHtml);
    $record = (new AjOsakaFetcher)->buildRecord($shops[1]);

    expect($record)->not->toBeNull()
        ->and($record['address'])->toBe('伊丹市下河原1-2-23') // ★改行除去
        ->and($record['city'])->toBe('伊丹市')
        ->and($record['tel'])->toBe('072-829-5099'); // ★ハイフン無し市外局番(3桁)→3-3-4
});

it('normalizes hyphenless phone numbers (03/06=2-4-4, 3-digit area=3-3-4) and keeps source hyphens', function () {
    $f = new AjOsakaFetcher;
    $mk = fn (string $tel) => $f->buildRecord(['id' => 's', 'name' => 'n', 'postal' => null, 'address' => '大阪府豊中市清風荘1-15-6', 'tel' => $tel, 'url' => 'u'])['tel'];

    expect($mk('0668765433'))->toBe('06-6876-5433')   // 06 → 2-4-4
        ->and($mk('0729235819'))->toBe('072-923-5819') // 072 → 3-3-4
        ->and($mk('0924085819'))->toBe('092-408-5819') // 092 → 3-3-4
        ->and($mk('09012345678'))->toBe('090-1234-5678') // 携帯 → 3-4-4
        ->and($mk('06-4866-6000'))->toBe('06-4866-6000'); // 元のハイフンは尊重
});

it('returns null when the address has no resolvable city', function () {
    $shop = ['id' => 's_X', 'name' => 'テスト', 'postal' => null, 'address' => '下河原1-2-23', 'tel' => null, 'url' => 'https://aj-rentalbike.com/shop_bike.php?shop=s_X'];
    expect((new AjOsakaFetcher)->buildRecord($shop))->toBeNull();
});

it('never returns image-related keys', function () use ($listHtml) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ((new AjOsakaFetcher)->extractShops($listHtml) as $shop) {
        $record = (new AjOsakaFetcher)->buildRecord($shop);
        if ($record !== null) {
            expect(array_intersect($banned, array_keys($record)))->toBe([]);
        }
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new AjOsakaFetcher;

    expect($f->slug())->toBe('aj-osaka')
        ->and($f->company())->toBe('大阪オートバイ事業協同組合')
        ->and($f->officialUrl())->toBe('https://aj-rentalbike.com/');
});
