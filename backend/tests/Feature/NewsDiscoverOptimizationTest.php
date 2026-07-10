<?php

use App\Console\Commands\GenerateSitemap;
use App\Models\BikeNews;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function origNews(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'MotoHub調べ 売れ筋ランキング', 'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL, 'published_at' => now(),
        'thumbnail_url' => 'https://motohub.jp/storage/models/1/1/0.jpg',
    ], $attrs));
}

function rssNews(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'よそのサイトの記事', 'url' => 'https://goo-net.com/'.uniqid(),
        'source' => 'goo-net.com', 'published_at' => now(),
    ], $attrs));
}

// ─────────── NewsArticle JSON-LD ───────────

it('outputs NewsArticle JSON-LD on an original news article', function () {
    $n = origNews(['title' => 'オリジナル記事タイトル']);

    $res = $this->get("/news/{$n->id}")->assertOk();
    $res->assertSee('application/ld+json', false)
        ->assertSee('"@type":"NewsArticle"', false)
        ->assertSee('"headline":"オリジナル記事タイトル"', false)
        ->assertSee('"datePublished"', false)
        ->assertSee('"publisher"', false)
        ->assertSee('"logo"', false);              // publisher.logo
});

it('does NOT output NewsArticle JSON-LD on an RSS (transplanted) article', function () {
    $n = rssNews(['title' => 'RSS記事タイトル']);

    $this->get("/news/{$n->id}")->assertOk()
        ->assertDontSee('"@type":"NewsArticle"', false); // 転載記事には自社ニュース構造化データを付けない
});

// ─────────── max-image-preview（既存の全体適用の確認・noindex非破壊） ───────────

it('emits max-image-preview:large on an indexable original article', function () {
    $n = origNews();
    $this->get("/news/{$n->id}")->assertOk()->assertSee('max-image-preview:large', false);
});

it('keeps an RSS article noindex without leaking max-image-preview', function () {
    $n = rssNews();
    $this->get("/news/{$n->id}")->assertOk()
        ->assertSee('noindex', false)
        ->assertDontSee('max-image-preview', false); // noindexページのrobots指定を壊さない
});

// ─────────── Googleニュース sitemap ───────────

it('selects only original articles from the last 2 days for the Google News sitemap', function () {
    origNews(['title' => '最近のオリジナル記事', 'published_at' => now()->subHours(3)]);
    origNews(['title' => '古いオリジナル記事', 'published_at' => now()->subDays(5)]);
    rssNews(['title' => '最近のRSS記事', 'published_at' => now()->subHours(1)]);

    $titles = GenerateSitemap::googleNewsArticles()->pluck('title')->all();

    expect($titles)->toContain('最近のオリジナル記事')   // 2日以内オリジナル → 含む
        ->not->toContain('古いオリジナル記事')          // 2日超 → 含まない
        ->not->toContain('最近のRSS記事');              // RSS → 含まない
});

it('renders a Google News sitemap entry with news:news tags', function () {
    $n = origNews(['title' => 'ニュースサイトマップ項目']);

    $xml = GenerateSitemap::renderNewsSitemapEntry($n);

    expect($xml)->toContain('<news:news>')
        ->toContain('<news:name>MotoHub</news:name>')
        ->toContain('<news:language>ja</news:language>')
        ->toContain('<news:publication_date>')
        ->toContain('ニュースサイトマップ項目')
        ->toContain('/news/'.$n->id);
});

it('uses sitemap-news.xml as the Google News sitemap filename (registered in the index on generate)', function () {
    expect(GenerateSitemap::GOOGLE_NEWS_SITEMAP)->toBe('sitemap-news.xml');
});

// ─────────── オリジナルRSSフィード（既存流用の確認） ───────────

it('serves an original-only RSS feed at /feed/news', function () {
    origNews(['title' => 'オリジナルRSS項目']);
    rssNews(['title' => '転載RSS項目']);

    $this->get('/feed/news')->assertOk()
        ->assertSee('オリジナルRSS項目')
        ->assertDontSee('転載RSS項目'); // 自サイトのフィードで他社記事は再配信しない
});
