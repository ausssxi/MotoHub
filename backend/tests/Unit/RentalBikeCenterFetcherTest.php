<?php

use App\Support\RentalBike\Fetchers\BikeCenterFetcher;

/**
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★住所→都道府県/市区町村が正しく取れること。
 */
$html = <<<'HTML'
<div class="pref">千葉県</div>
<ul>
  <li>
    <p class="name">スーパーバイクセンター 千葉本店</p>
    <p class="addr">〒285-0856 千葉県佐倉市井野町60-41</p>
    <p class="tel">TEL:043-420-8464 / FAX:043-420-8465</p>
  </li>
  <li>
    <p class="name">スーパーバイクセンター 幕張店</p>
    <p class="addr">〒262-0032 千葉県千葉市花見川区幕張町1-2-3</p>
    <p class="tel">TEL:043-000-0000 / FAX:043-000-0001</p>
  </li>
</ul>
<div class="pref">東京都</div>
<ul>
  <li>
    <p class="name">スーパーバイクセンター 江戸川店</p>
    <p class="addr">〒133-0057 東京都江戸川区西小岩1-4-7</p>
    <p class="tel">TEL:03-1234-5678 / FAX:03-1234-5679</p>
  </li>
</ul>
HTML;

it('parses each store with name / postal / address / prefecture / city / tel', function () use ($html) {
    $records = (new BikeCenterFetcher)->parse($html);

    expect($records)->toHaveCount(3);

    $first = $records[0];
    expect($first['name'])->toBe('スーパーバイクセンター 千葉本店')
        ->and($first['postal_code'])->toBe('285-0856')
        ->and($first['address'])->toBe('千葉県佐倉市井野町60-41')
        ->and($first['prefecture'])->toBe('千葉県')
        ->and($first['city'])->toBe('佐倉市')
        ->and($first['tel'])->toBe('043-420-8464'); // ★FAXは拾わない
});

it('extracts city for designated-city ward and Tokyo special ward', function () use ($html) {
    $records = (new BikeCenterFetcher)->parse($html);

    expect($records[1]['city'])->toBe('千葉市花見川区')   // 政令市＋区
        ->and($records[2]['prefecture'])->toBe('東京都')
        ->and($records[2]['city'])->toBe('江戸川区');      // 特別区
});

it('never returns image-related keys', function () use ($html) {
    $records = (new BikeCenterFetcher)->parse($html);

    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ($records as $record) {
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});

it('exposes public identity (slug / company / official url)', function () {
    $fetcher = new BikeCenterFetcher;
    expect($fetcher->slug())->toBe('bikecenter')
        ->and($fetcher->company())->toBe('レンタルバイクセンター')
        ->and($fetcher->officialUrl())->toStartWith('https://');
});
