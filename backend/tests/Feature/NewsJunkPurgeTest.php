<?php

declare(strict_types=1);

use App\Console\Commands\FetchBikeNews;
use App\Models\BikeNews;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rssNews(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'RSS記事',
        'url' => 'https://news.google.com/'.uniqid(),
        'source' => 'Webike',
        'published_at' => now(),
    ], $attrs));
}

// ─────────── 修正2: 30日重複の保険 ───────────

it('treats a same title+source within 30 days as a recent duplicate', function () {
    rssNews(['title' => '新型Ninja 250が発表', 'source' => 'Webike', 'created_at' => now()->subDays(10)]);

    $cmd = new FetchBikeNews;

    expect($cmd->hasRecentDuplicate('新型Ninja 250が発表', 'Webike'))->toBeTrue();
    // 別 source は別物。
    expect($cmd->hasRecentDuplicate('新型Ninja 250が発表', 'Response'))->toBeFalse();
});

it('does not treat a 31-day-old record as a recent duplicate', function () {
    $n = rssNews(['title' => '新型Ninja 250が発表', 'source' => 'Webike']);
    $n->forceFill(['created_at' => now()->subDays(31)])->save();

    $cmd = new FetchBikeNews;

    expect($cmd->hasRecentDuplicate('新型Ninja 250が発表', 'Webike'))->toBeFalse();
});

// ─────────── 修正3: purge コマンド ───────────

it('dry-run reports junk but deletes nothing', function () {
    rssNews(['title' => 'ホーム｜MOTOCLE', 'source' => 'MOTOCLE']);
    rssNews(['title' => 'Z900RSの投稿一覧', 'source' => 'みんカラ']);
    $before = BikeNews::count();

    $this->artisan('news:purge-junk', ['--dry-run' => true])->assertSuccessful();

    expect(BikeNews::count())->toBe($before);
});

it('deletes junk external RSS records but keeps legitimate ones', function () {
    $junk1 = rssNews(['title' => 'ホーム｜MOTOCLE', 'source' => 'MOTOCLE']);
    $junk2 = rssNews(['title' => 'Z900RSの投稿一覧', 'source' => 'みんカラ']);
    $junk3 = rssNews(['title' => 'GooBike(グーバイク)', 'source' => 'GooBike']);
    $legit = rssNews(['title' => '新型Ninja 250が発表', 'source' => 'Webike']);

    $this->artisan('news:purge-junk')->assertSuccessful();

    expect(BikeNews::find($junk1->id))->toBeNull();
    expect(BikeNews::find($junk2->id))->toBeNull();
    expect(BikeNews::find($junk3->id))->toBeNull();
    expect(BikeNews::find($legit->id))->not->toBeNull();
});

it('never deletes MotoHub (own) articles even if they look junk', function () {
    $own = BikeNews::create([
        'title' => 'ホーム｜MOTOCLE', // わざとゴミ形だが自前ソース
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'published_at' => now(),
    ]);

    $this->artisan('news:purge-junk')->assertSuccessful();

    expect(BikeNews::find($own->id))->not->toBeNull();
});

it('never deletes junk that has comments', function () {
    $commented = rssNews(['title' => 'Z900RSの投稿一覧', 'source' => 'みんカラ', 'comments_count' => 3]);

    $this->artisan('news:purge-junk')->assertSuccessful();

    expect(BikeNews::find($commented->id))->not->toBeNull();
});
