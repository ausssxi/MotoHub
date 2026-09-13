<?php

declare(strict_types=1);

use App\Support\NewsJunkFilter;

it('excludes home-pipe top pages', function () {
    expect(NewsJunkFilter::isJunk('ホーム｜MOTOCLE', 'MOTOCLE'))->toBeTrue();   // 全角パイプ
    expect(NewsJunkFilter::isJunk('ホーム |MOTOCLE', 'MOTOCLE'))->toBeTrue();   // 半角パイプ＋空白
    expect(NewsJunkFilter::junkReason('ホーム｜MOTOCLE', 'MOTOCLE'))->toBe('home_pipe');
});

it('does NOT exclude legitimate articles that merely start with ホーム', function () {
    // ★重要: 「ホーム」始まりで巻き込まないこと。
    expect(NewsJunkFilter::isJunk('ホームセンターで買えるバイク用品', 'Webike'))->toBeFalse();
});

it('excludes bulletin-board list pages', function () {
    expect(NewsJunkFilter::isJunk('Z900RSの投稿一覧', 'みんカラ'))->toBeTrue();
    expect(NewsJunkFilter::junkReason('Z900RSの投稿一覧', 'みんカラ'))->toBe('post_list');
    expect(NewsJunkFilter::isJunk('MONKEY125の投稿', 'みんカラ'))->toBeTrue();
    expect(NewsJunkFilter::junkReason('MONKEY125の投稿', 'みんカラ'))->toBe('post');
});

it('excludes titles that are essentially just the site name', function () {
    expect(NewsJunkFilter::isJunk('GooBike(グーバイク)', 'GooBike'))->toBeTrue();
    expect(NewsJunkFilter::junkReason('GooBike(グーバイク)', 'GooBike'))->toBe('site_name');
    // source が空なら site_name 判定はしない。
    expect(NewsJunkFilter::junkReason('GooBike(グーバイク)', ''))->toBeNull();
});

it('excludes too-short or empty titles', function () {
    expect(NewsJunkFilter::isJunk('', 'X'))->toBeTrue();
    expect(NewsJunkFilter::isJunk('新型', 'X'))->toBeTrue();      // 2文字
    expect(NewsJunkFilter::junkReason('1234', 'X'))->toBe('too_short'); // 4文字
    expect(NewsJunkFilter::junkReason('バイク速報だ', 'X'))->toBeNull();  // 5文字以上の日本語は通す
});

it('excludes slug / image-page titles (slug_like)', function () {
    expect(NewsJunkFilter::junkReason('image', 'Motor-Fan'))->toBe('slug_like');
    expect(NewsJunkFilter::junkReason('oppo_2', 'Motor-Fan'))->toBe('slug_like');
    // 「| の前」が slug 状ならゴミ。
    expect(NewsJunkFilter::junkReason('oppo_2 | Motor-Fan[モーターファン] 自動車関連ニュース', 'Motor-Fan'))
        ->toBe('slug_like');
});

it('does NOT exclude english article titles because they contain spaces', function () {
    // ★重要: スペースを含む英語記事は通す。
    expect(NewsJunkFilter::isJunk('New Ninja 250 unveiled', 'Bennetts'))->toBeFalse();
});

it('excludes non-news sources (partial, case-insensitive) passed in', function () {
    $excluded = ['GooBike', 'goobike.com', 'グーバイク'];

    expect(NewsJunkFilter::junkReason('ホンダトゥデイ・Ｆ 外装新品タイヤ4分山', 'GooBike', $excluded))
        ->toBe('excluded_source');
    expect(NewsJunkFilter::junkReason('Under125[スポーツ系]', 'goobike.com', $excluded))
        ->toBe('excluded_source');
    // 別 source は除外しない。
    expect(NewsJunkFilter::junkReason('新型が登場したというニュース', 'ヤングマシン', $excluded))
        ->toBeNull();
});

it('keeps 試乗レポート even from an excluded source', function () {
    // ★重要: GooBike の試乗レポートは記事として成立するので残す。
    $excluded = ['GooBike', 'goobike.com', 'グーバイク'];

    expect(NewsJunkFilter::junkReason('デルビ ランブラ 250i 試乗レポート｜懐かしの2スト', 'GooBike', $excluded))
        ->toBeNull();
});

it('does not exclude sources unless the list is provided (no hardcoding)', function () {
    // リスト未指定なら GooBike でも除外しない＝ハードコードされていないことの確認。
    expect(NewsJunkFilter::junkReason('ホンダトゥデイ・Ｆ 外装新品タイヤ4分山', 'GooBike'))->toBeNull();
});

it('keeps legitimate news articles', function () {
    expect(NewsJunkFilter::isJunk('新型Ninja 250が発表', 'Webike'))->toBeFalse();
    expect(NewsJunkFilter::isJunk('Z900RSの新型マフラーが登場', 'Response'))->toBeFalse();
    expect(NewsJunkFilter::junkReason('ホンダCB400の生産終了を発表', 'Car Watch'))->toBeNull();
    expect(NewsJunkFilter::junkReason('ホンダ・ADV160は通勤も楽しい', 'WEB Mr.Bike'))->toBeNull();
});
