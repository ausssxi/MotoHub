<?php

use App\Support\RentalBike\Fetchers\Rental819Fetcher;

/**
 * レンタル819 Fetcher の単体テスト。
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（★外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★取れない項目（TEL/営業時間）は null で残ること。
 * ★住所→都道府県/市区町村は既存 AddressParser に揃うこと。
 * ★取得ヘッダ（Accept-Language: ja）の回帰は tests/Feature/RentalBikeFetchCommandTest.php（要アプリbootstrap）。
 *
 * 一覧の断片は本番HTMLの実構造（2026-09 確認・日本語版 ~/rental819-store-list-ja.html）:
 *   <div class="p-store-list__area-wrap">
 *     <p class="p-store-list__pref">北海道</p>              ← ★都道府県見出し（日本語フルネーム）
 *     <div class="p-store-list__area-inner">
 *       <a href="/store/{id}">
 *         <span class="p-store-list__store-name"><i class="las la-store-alt"></i>店名</span>
 *         <span class="p-store-list__store-adress">市区町村＋番地（都道府県は省略）</span>
 *       </a>
 *     </div>
 *   </div>
 * ★店名 span 内の <i> アイコンはタグ除去で落とす（アイコンが店名に混入していた不具合の回帰防止）。
 * ★都道府県は「直前の p-store-list__pref 見出し」から取る（詳細に都道府県が無い店＝特別区・一般市でも埋まる）。
 *   市区町村・番地は詳細ページの住所から取る。
 */

// 一覧ページ断片（実構造）。都道府県見出しごとに店舗が並ぶ。
// 重複ID・店名spanを持たない /store/ リンク・/store/ トップは店舗ではない。
$listHtml = <<<'HTML'
<div class="p-store-list__area-wrap">
  <p id="prefecture_1" class="p-store-list__pref">北海道</p>
  <div class="p-store-list__area-inner">
    <a href="/store/39">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>北見店</span>
      <span class="p-store-list__store-adress">北見市並木町144-19</span>
    </a>
  </div>
</div>
<div class="p-store-list__area-wrap">
  <p id="prefecture_13" class="p-store-list__pref">東京都</p>
  <div class="p-store-list__area-inner">
    <a href="/store/31">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>お台場店</span>
      <span class="p-store-list__store-adress">港区台場1-6-1</span>
    </a>
  </div>
  <div class="p-store-list__area-inner">
    <a href="/store/39">
      <span class="p-store-list__store-name"><i class="las la-store-alt"></i>北見店（重複導線）</span>
    </a>
  </div>
</div>
<a href="/store/">店舗一覧トップ</a>
<nav><a href="/store/999">フッターのstoreリンク（店名span無し）</a></nav>
HTML;

// 店舗詳細（日本語）: 〒・住所（都道府県込み）・TEL・営業時間が揃うケース。
$detailFull = <<<'HTML'
<main>
  <div class="info">
    <p>〒135-0091</p>
    <p>東京都港区台場1-6-1 DECKS東京ビーチ シーサイドモール1F</p>
    <p>TEL：03-3599-2235</p>
    <p>営業時間：11:00〜20:00</p>
  </div>
</main>
HTML;

// 店舗詳細（日本語）: TEL・営業時間が無いケース（→ null で残す）。
$detailNoTel = <<<'HTML'
<main>
  <div class="info">
    <p>〒090-0037</p>
    <p>北海道北見市並木町144-19</p>
  </div>
</main>
HTML;

it('extracts store name + detail url + prefecture from the list, stripping the icon and dupes/non-stores', function () use ($listHtml) {
    $stores = (new Rental819Fetcher)->extractStores($listHtml);

    expect($stores)->toHaveCount(2);

    expect($stores[0]['id'])->toBe('39')
        ->and($stores[0]['url'])->toBe('https://rental819.com/store/39')
        ->and($stores[0]['name'])->toBe('北見店')     // ★<i> アイコンは混入しない
        ->and($stores[0]['prefecture'])->toBe('北海道'); // ★直前の見出しから

    expect($stores[1]['id'])->toBe('31')
        ->and($stores[1]['name'])->toBe('お台場店')
        ->and($stores[1]['prefecture'])->toBe('東京都'); // ★見出しが変われば追従

    // 店名は英字クラス片やアイコン文字を含まない。
    expect($stores[0]['name'])->not->toContain('la-store')
        ->and($stores[0]['name'])->not->toContain('las');
});

it('builds a record using the list name + prefecture and the detail address / postal / tel / hours', function () use ($detailFull) {
    $store = ['id' => '31', 'url' => 'https://rental819.com/store/31', 'name' => 'お台場店', 'prefecture' => '東京都'];
    $record = (new Rental819Fetcher)->buildRecord($store, $detailFull);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('お台場店')                 // ★店名は一覧から
        ->and($record['postal_code'])->toBe('135-0091')
        ->and($record['address'])->toBe('東京都港区台場1-6-1 DECKS東京ビーチ シーサイドモール1F') // ★市区町村・番地は詳細から
        ->and($record['prefecture'])->toBe('東京都')             // ★都道府県は一覧見出しから
        ->and($record['city'])->toBe('港区')
        ->and($record['tel'])->toBe('03-3599-2235')
        ->and($record['opening_hours'])->toBe('11:00〜20:00')
        ->and($record['external_id'])->toBe('31')
        ->and($record['official_url'])->toBe('https://rental819.com/store/31');
});

// ★核心の回帰: 詳細ページに都道府県が1度も出ない店（特別区・一般市）でも、一覧見出しの都道府県で埋まる。
//   以前はこの手の店が buildRecord で null 落ちして 103→32 に脱落していた。
it('fills prefecture from the list heading even when the detail address has none (Tokyo ward)', function () {
    $store = ['id' => '3', 'url' => 'https://rental819.com/store/3', 'name' => '阿佐ヶ谷店', 'prefecture' => '東京都'];
    // 詳細ページの住所には「東京都」が一切出てこない（本番 store/3 と同じ状況）。
    $detail = '<main><p>〒166-0001</p><p>杉並区阿佐谷北4-27-3</p></main>';

    $record = (new Rental819Fetcher)->buildRecord($store, $detail);

    expect($record)->not->toBeNull()
        ->and($record['postal_code'])->toBe('166-0001')
        ->and($record['prefecture'])->toBe('東京都')                 // ★一覧見出しから補完
        ->and($record['city'])->toBe('杉並区')                       // ★市区町村は詳細から
        ->and($record['address'])->toBe('東京都杉並区阿佐谷北4-27-3'); // ★都道府県を前置して連結
});

it('leaves tel and hours null when the detail page has none', function () use ($detailNoTel) {
    $store = ['id' => '39', 'url' => 'https://rental819.com/store/39', 'name' => '北見店', 'prefecture' => '北海道'];
    $record = (new Rental819Fetcher)->buildRecord($store, $detailNoTel);

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('北見店')
        ->and($record['postal_code'])->toBe('090-0037')
        ->and($record['prefecture'])->toBe('北海道')
        ->and($record['city'])->toBe('北見市')
        ->and($record['tel'])->toBeNull()        // ★無理に埋めない
        ->and($record['opening_hours'])->toBeNull();
});

it('returns null when no city can be resolved from the detail address', function () {
    // 一覧の都道府県があっても、市区町村すら取れない詳細 → 地図に出せないのでレコードにしない。
    $store = ['id' => '1', 'url' => 'https://rental819.com/store/1', 'name' => 'テスト店', 'prefecture' => '北海道'];
    expect((new Rental819Fetcher)->buildRecord($store, '<main><p>並木町144-19</p></main>'))->toBeNull();
});

it('never returns image-related keys', function () use ($detailFull, $detailNoTel) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ([$detailFull, $detailNoTel] as $i => $html) {
        $record = (new Rental819Fetcher)->buildRecord(
            ['id' => (string) $i, 'url' => "https://rental819.com/store/{$i}", 'name' => '店'],
            $html
        );
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new Rental819Fetcher;

    expect($f->slug())->toBe('rental819')
        ->and($f->company())->toBe('レンタル819')
        ->and($f->officialUrl())->toBe('https://rental819.com/');
});
