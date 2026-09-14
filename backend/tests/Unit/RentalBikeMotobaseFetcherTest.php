<?php

use App\Support\RentalBike\Fetchers\MotobaseFetcher;

/**
 * ★保存したHTMLの断片を食わせて期待する配列が返ること（外部アクセスはしない）。
 * ★返る配列に画像キーが無いこと。★〒/TEL が無い拠点は null で残ること。
 */
$hubHtml = <<<'HTML'
<ul>
  <li><a href="/rental-bike/tokyo/setagaya-ku">世田谷区(2拠点)</a></li>
  <li><a href="/rental-bike/tokyo/shinjuku-ku">新宿区(1拠点)</a></li>
  <li><a href="/rental-bike/tokyo">東京都</a></li>
  <li><a href="/rental-bike/tokyo/setagaya-ku/jiyugaoka">詳細を見る</a></li>
  <li><a href="/rental-bike/kanagawa/yokohama">神奈川</a></li>
</ul>
HTML;

$wardHtml = <<<'HTML'
<section>
  <h3>自由が丘ベース</h3>
  <a href="/rental-bike/tokyo/setagaya-ku/jiyugaoka">詳細を見る</a>
  <div class="info">
    <span class="label">住所</span>
    <p>東京都世田谷区奥沢3-13-6</p>
    <span class="label">アクセス</span>
    <p>東急目黒線「奥沢駅」徒歩4分</p>
  </div>
  <h3>世田谷上祖師谷ベース</h3>
  <a href="/rental-bike/tokyo/setagaya-ku/kamisoshigaya">詳細を見る</a>
  <div class="info">
    <span class="label">住所</span>
    <p>東京都世田谷区上祖師谷2-6-11</p>
  </div>
</section>
HTML;

it('extracts only single-segment ward URLs from the hub (excludes hub self / detail / other prefecture)', function () use ($hubHtml) {
    $urls = (new MotobaseFetcher)->extractWardUrls($hubHtml);

    expect($urls)->toBe([
        'https://motobase.jp/rental-bike/tokyo/setagaya-ku',
        'https://motobase.jp/rental-bike/tokyo/shinjuku-ku',
    ]);
});

it('parses each location in a ward page with name / address / prefecture / city / detail url', function () use ($wardHtml) {
    $records = (new MotobaseFetcher)->parseWard($wardHtml);

    expect($records)->toHaveCount(2);

    expect($records[0]['name'])->toBe('自由が丘ベース')
        ->and($records[0]['address'])->toBe('東京都世田谷区奥沢3-13-6')
        ->and($records[0]['prefecture'])->toBe('東京都')
        ->and($records[0]['city'])->toBe('世田谷区')
        ->and($records[0]['official_url'])->toBe('https://motobase.jp/rental-bike/tokyo/setagaya-ku/jiyugaoka')
        ->and($records[0]['postal_code'])->toBeNull()   // 区ページに〒は無い
        ->and($records[0]['tel'])->toBeNull();          // 区ページにTELは無い

    expect($records[1]['name'])->toBe('世田谷上祖師谷ベース')
        ->and($records[1]['city'])->toBe('世田谷区');
});

it('never returns image-related keys', function () use ($wardHtml) {
    $records = (new MotobaseFetcher)->parseWard($wardHtml);

    $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
    foreach ($records as $record) {
        expect(array_intersect($banned, array_keys($record)))->toBe([]);
    }
});
