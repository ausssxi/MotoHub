<?php

use App\Support\RentalBike\Fetchers\Rental819Fetcher;

/**
 * レンタル819 Fetcher の単体テスト。
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（★外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★取れない項目（TEL/営業時間）は null で残ること。
 * ★住所→都道府県/市区町村は既存 AddressParser に揃うこと。
 */

// 一覧ページ断片: /store/{数値ID} のみを店舗とみなす。一覧トップ /store/ 自身や他導線は除外・重複は潰す。
$listHtml = <<<'HTML'
<div class="store-list">
  <h2>関東エリア</h2>
  <ul>
    <li><a href="/store/31">お台場店</a></li>
    <li><a href="/store/1">秋田店</a></li>
    <li><a href="/store/31">お台場店（別導線・重複）</a></li>
    <li><a href="/store/">店舗一覧トップ</a></li>
    <li><a href="/search/">バイク検索</a></li>
  </ul>
</div>
HTML;

// 店舗詳細（日本語）: 店名(h1)・〒・住所・TEL・営業時間 が揃うケース。
$detailFull = <<<'HTML'
<main>
  <h1>お台場店</h1>
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
  <h1>秋田店</h1>
  <div class="info">
    <p>〒010-0951</p>
    <p>秋田県秋田市山王3-1-1</p>
  </div>
</main>
HTML;

it('extracts unique /store/{id} detail urls (excludes list top / non-store links / dupes)', function () use ($listHtml) {
    $urls = (new Rental819Fetcher)->extractStoreUrls($listHtml);

    expect($urls)->toBe([
        'https://www.rental819.com/store/31',
        'https://www.rental819.com/store/1',
    ]);
});

it('parses a full detail page into name / postal / address / prefecture / city / tel / hours', function () use ($detailFull) {
    $record = (new Rental819Fetcher)->parseDetail($detailFull, 'https://www.rental819.com/store/31');

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('お台場店')
        ->and($record['postal_code'])->toBe('135-0091')
        ->and($record['address'])->toBe('東京都港区台場1-6-1 DECKS東京ビーチ シーサイドモール1F')
        ->and($record['prefecture'])->toBe('東京都')   // ★AddressParser に揃う
        ->and($record['city'])->toBe('港区')            // ★特別区も AddressParser で city になる
        ->and($record['tel'])->toBe('03-3599-2235')
        ->and($record['opening_hours'])->toBe('11:00〜20:00')
        ->and($record['external_id'])->toBe('31')
        ->and($record['official_url'])->toBe('https://www.rental819.com/store/31');
});

it('leaves tel and hours null when the detail page has none', function () use ($detailNoTel) {
    $record = (new Rental819Fetcher)->parseDetail($detailNoTel, 'https://www.rental819.com/store/1');

    expect($record)->not->toBeNull()
        ->and($record['name'])->toBe('秋田店')
        ->and($record['postal_code'])->toBe('010-0951')
        ->and($record['prefecture'])->toBe('秋田県')
        ->and($record['city'])->toBe('秋田市')
        ->and($record['tel'])->toBeNull()        // ★無理に埋めない
        ->and($record['opening_hours'])->toBeNull();
});

it('never returns image-related keys', function () use ($detailFull, $detailNoTel) {
    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ([$detailFull, $detailNoTel] as $i => $html) {
        $record = (new Rental819Fetcher)->parseDetail($html, "https://www.rental819.com/store/{$i}");
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $f = new Rental819Fetcher;

    expect($f->slug())->toBe('rental819')
        ->and($f->company())->toBe('レンタル819')
        ->and($f->officialUrl())->toBe('https://www.rental819.com/');
});
