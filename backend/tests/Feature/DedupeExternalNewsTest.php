<?php

declare(strict_types=1);

use App\Models\BikeNews;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function extNews(array $attrs = []): BikeNews
{
    return BikeNews::create(array_merge([
        'title' => 'PCX譲りの完成度に冒険心をプラス！ ホンダ・ADV160は',
        'url' => 'https://news.google.com/'.uniqid(),
        'source' => 'WEB Mr.Bike',
        'published_at' => now(),
    ], $attrs));
}

it('keeps one and deletes the rest within a title+source group', function () {
    $a = extNews();
    $b = extNews();
    $c = extNews();

    $this->artisan('news:dedupe-external')->assertSuccessful();

    expect(BikeNews::whereIn('id', [$a->id, $b->id, $c->id])->count())->toBe(1);
    // 全て同条件なので id 最小が残る。
    expect(BikeNews::find($a->id))->not->toBeNull();
});

it('keeps the commented record as the survivor', function () {
    $first = extNews();                                   // id 最小・コメント無し
    $commented = extNews(['comments_count' => 2]);        // コメント有り

    $this->artisan('news:dedupe-external')->assertSuccessful();

    // コメント有りが残り、コメント無しの方が消える。
    expect(BikeNews::find($commented->id))->not->toBeNull();
    expect(BikeNews::find($first->id))->toBeNull();
});

it('prefers thumbnail then bike_model_id when no comments', function () {
    $plain = extNews();                                   // id 最小・装飾無し
    $withThumb = extNews(['thumbnail_url' => 'https://img/x.jpg']);

    $this->artisan('news:dedupe-external')->assertSuccessful();

    expect(BikeNews::find($withThumb->id))->not->toBeNull();
    expect(BikeNews::find($plain->id))->toBeNull();
});

it('does not delete a non-keeper that has comments (comment safety)', function () {
    $keeperCommented = extNews(['comments_count' => 5]);
    $otherCommented = extNews(['comments_count' => 1]);
    $plain = extNews();

    $this->artisan('news:dedupe-external')->assertSuccessful();

    // コメント付きは2件とも残る。素の1件だけ消える。
    expect(BikeNews::find($keeperCommented->id))->not->toBeNull();
    expect(BikeNews::find($otherCommented->id))->not->toBeNull();
    expect(BikeNews::find($plain->id))->toBeNull();
});

it('never touches MotoHub (own) articles', function () {
    $own1 = BikeNews::create([
        'title' => '同じタイトルの自前記事',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'published_at' => now(),
    ]);
    $own2 = BikeNews::create([
        'title' => '同じタイトルの自前記事',
        'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL,
        'published_at' => now(),
    ]);

    $this->artisan('news:dedupe-external')->assertSuccessful();

    expect(BikeNews::find($own1->id))->not->toBeNull();
    expect(BikeNews::find($own2->id))->not->toBeNull();
});

it('dry-run does not change the DB', function () {
    extNews();
    extNews();
    $before = BikeNews::count();

    $this->artisan('news:dedupe-external', ['--dry-run' => true])->assertSuccessful();

    expect(BikeNews::count())->toBe($before);
});

it('leaves non-duplicate external records alone', function () {
    $x = extNews(['title' => '固有タイトルA']);
    $y = extNews(['title' => '固有タイトルB']);

    $this->artisan('news:dedupe-external')->assertSuccessful();

    expect(BikeNews::find($x->id))->not->toBeNull();
    expect(BikeNews::find($y->id))->not->toBeNull();
});
