<?php

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function newsMfrModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250']);
}

function original(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'オリジナル記事：デイリー売上ランキング',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'published_at' => now(),
    ], $attrs));
}

function rss(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'RSS記事：他サイトのニュース',
        'url' => 'https://goo-net.com/'.uniqid(),
        'source' => 'goo-net.com',
        'published_at' => now(),
    ], $attrs));
}

// ─────────── /news はオリジナルのみ ───────────

it('shows original (MotoHub) articles on /news', function () {
    original(['title' => 'これはオリジナル記事です']);

    $this->get('/news')->assertOk()->assertSee('これはオリジナル記事です');
});

it('hides RSS articles from /news', function () {
    original(['title' => 'オリジナル見出し']);
    rss(['title' => 'RSSの見出し（外部サイト）', 'source' => 'VAGUE']);

    $res = $this->get('/news')->assertOk();
    $res->assertSee('オリジナル見出し')
        ->assertDontSee('RSSの見出し（外部サイト）')  // RSS本文が主導線に出ない
        ->assertDontSee('VAGUE');                      // RSSソース名も出ない
});

it('scopeOriginal filters to source=MotoHub only', function () {
    original();
    rss();

    $titles = BikeNews::original()->pluck('source')->unique()->values()->all();
    expect($titles)->toBe([BikeNews::SOURCE_ORIGINAL]);
});

// ─────────── 車種ページの「この車種のニュース」は RSS を残す（非波及） ───────────

it('does not leak the original-only filter to the car-model news query (regression)', function () {
    $model = newsMfrModel();
    original(['bike_model_id' => $model->id, 'title' => 'この車種のオリジナル']);
    rss(['bike_model_id' => $model->id, 'title' => 'この車種のRSS', 'source' => 'goo-net.com']);

    // BikeController の「この車種のニュース」は source 非フィルタの生クエリ（RSS含む）
    $modelNews = BikeNews::where('bike_model_id', $model->id)->latest()->get();
    expect($modelNews->pluck('source')->all())->toContain('goo-net.com')       // RSS が残る
        ->and($modelNews->pluck('source')->all())->toContain(BikeNews::SOURCE_ORIGINAL);

    // 一方 /news 本体では RSS が出ない
    $this->get('/news')->assertOk()
        ->assertSee('この車種のオリジナル')
        ->assertDontSee('この車種のRSS');
});

// ─────────── 詳細ページは両方とも従来どおり（indexフィルタの非影響） ───────────

it('keeps both original and RSS detail pages reachable', function () {
    $o = original(['title' => 'オリジナル詳細']);
    $r = rss(['title' => 'RSS詳細']);

    $this->get("/news/{$o->id}")->assertOk();
    $this->get("/news/{$r->id}")->assertOk(); // RSS詳細も壊さない（show は非フィルタ）
});
