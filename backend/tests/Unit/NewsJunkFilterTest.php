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
    expect(NewsJunkFilter::junkReason('12345', 'X'))->toBeNull();       // 5文字は通す
});

it('keeps legitimate news articles', function () {
    expect(NewsJunkFilter::isJunk('新型Ninja 250が発表', 'Webike'))->toBeFalse();
    expect(NewsJunkFilter::isJunk('Z900RSの新型マフラーが登場', 'Response'))->toBeFalse();
    expect(NewsJunkFilter::junkReason('ホンダCB400の生産終了を発表', 'Car Watch'))->toBeNull();
});
