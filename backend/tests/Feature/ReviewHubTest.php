<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;

function reviewHubModel(string $mfrName, string $mfrSlug, string $name, string $slug, int $cc): BikeModel
{
    $mfr = Manufacturer::forceCreate(['name' => $mfrName, 'slug' => $mfrSlug]);

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug, 'displacement' => $cc]);
}

function makeReview(int $modelId, string $nickname, string $title, int $rating = 5): Review
{
    return Review::create([
        'bike_model_id' => $modelId, 'nickname' => $nickname, 'title' => $title,
        'body' => '使ってみての感想です。', 'rating' => $rating, 'is_approved' => true,
    ]);
}

it('reviews hub: 200, feed, maker filter, hides テスト seeds, shows CTA, no Review/Rating schema', function () {
    $honda = reviewHubModel('Honda', 'honda', 'PCX', 'pcx', 125);
    $yamaha = reviewHubModel('Yamaha', 'yamaha', 'R1', 'r1', 1000);

    makeReview($honda->id, 'たろう', 'よく走る良いバイク', 5);
    makeReview($yamaha->id, 'じろう', '速くて最高', 4);
    makeReview($honda->id, 'テスト', 'テスト投稿', 3); // 明らかなシード → 非表示

    $res = $this->get('/bikes/reviews')
        ->assertOk()
        ->assertSee('バイク 実車レビュー一覧')
        ->assertSee('よく走る良いバイク')   // 通常レビュー
        ->assertSee('速くて最高')
        ->assertSee('あなたのバイクのレビューを書く') // 投稿CTA
        ->assertSee('Honda')                 // メーカーフィルタ chip
        ->assertSee('Yamaha')
        ->assertDontSee('テスト投稿');       // "テスト"系は非表示

    // 個別 Review/Rating の JSON-LD は付けない（CollectionPage のみ）
    $res->assertSee('CollectionPage')
        ->assertDontSee('AggregateRating')
        ->assertDontSee('aggregateRating')
        ->assertDontSee('"@@type": "Review"', false);
});

it('reviews hub: maker filter narrows the feed', function () {
    $honda = reviewHubModel('Honda', 'honda', 'PCX', 'pcx', 125);
    $yamaha = reviewHubModel('Yamaha', 'yamaha', 'R1', 'r1', 1000);
    makeReview($honda->id, 'たろう', 'ホンダのレビュー本文', 5);
    makeReview($yamaha->id, 'じろう', 'ヤマハのレビュー本文', 4);

    $this->get('/bikes/reviews?maker='.$honda->manufacturer_id)
        ->assertOk()
        ->assertSee('ホンダのレビュー本文')
        ->assertDontSee('ヤマハのレビュー本文'); // Yamaha は除外
});

it('home page links to the reviews hub (nav + TOP section)', function () {
    $honda = reviewHubModel('Honda', 'honda', 'PCX', 'pcx', 125);
    makeReview($honda->id, 'たろう', 'トップ用レビュー', 5); // TOP の最新レビュー section を出す

    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('bikes.reviews_index').'"', false) // ナビ＋TOP導線
        ->assertSee('すべてのレビューを見る'); // TOP のハブ導線
});
