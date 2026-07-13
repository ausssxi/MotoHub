<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Report;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rsModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250', 'displacement' => 250]);
}

function rsReview(BikeModel $m, int $rating, bool $approved = true): Review
{
    return Review::create([
        'bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 't', 'body' => 'b',
        'rating' => $rating, 'is_approved' => $approved,
    ]);
}

// ─────────── 「参考になった」投票 ───────────

it('lets a user mark a review helpful, with duplicate votes suppressed', function () {
    $m = rsModel();
    $r = rsReview($m, 5);

    $this->post("/bikes/reviews/{$r->id}/helpful")->assertOk()->assertJson(['helpful_count' => 1]);
    $this->post("/bikes/reviews/{$r->id}/helpful")->assertOk()->assertJson(['helpful_count' => 1]); // 重複は増えない

    expect($r->fresh()->helpful_count)->toBe(1)
        ->and(ReviewHelpfulVote::count())->toBe(1);
});

it('does not accept helpful votes on unapproved reviews', function () {
    $m = rsModel();
    $r = rsReview($m, 5, approved: false);

    $this->post("/bikes/reviews/{$r->id}/helpful")->assertNotFound();
});

// ─────────── AggregateRating スキーマ（ゲート：approved 2件以上） ───────────

it('emits AggregateRating only when 2+ approved reviews exist', function () {
    $m = rsModel();
    $stats = ['count' => 5, 'min_raw' => 100000, 'max_raw' => 500000];

    $with2 = view('components.jsonld.model-product', ['model' => $m, 'stats' => $stats, 'reviewStats' => ['count' => 2, 'avg_rating' => 4.5]])->render();
    $with1 = view('components.jsonld.model-product', ['model' => $m, 'stats' => $stats, 'reviewStats' => ['count' => 1, 'avg_rating' => 5.0]])->render();

    expect($with2)->toContain('AggregateRating')->toContain('"ratingValue": 4.5')->toContain('"reviewCount": 2')
        ->and($with1)->not->toContain('AggregateRating'); // 1件では出さない（薄い/自作自演回避）
});

// ─────────── 集計は承認済みのみ（シード除外）＋分布 ───────────

it('filters the review aggregation to approved only and computes the distribution', function () {
    $src = file_get_contents(app_path('Http/Controllers/Bike/BikeController.php'));

    // reviewStats/分布が is_approved=true 絞り込み＋分布算出になっている
    expect($src)->toContain("->where('bike_model_id', \$id)->where('is_approved', true)")
        ->toContain('$reviewDistribution')
        ->toContain("'reviewDistribution'"); // ビューへ渡している
});

// ─────────── 表示UI（サマリ・ソート・投票・報告） ───────────

it('renders the summary bars, sort toggle, helpful and report controls', function () {
    $b = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));

    expect($b)->toContain('$reviewDistribution as $star => $cnt')      // ★分布バー
        ->toContain('review_sort')                                     // ソート切替
        ->toContain("route('bikes.review.helpful'")                    // 参考になった投票
        ->toContain("'type' => 'review'")                              // 通報導線（モデレーション）
        ->toContain('$approvedReviews = $model->reviews->where(\'is_approved\', true)'); // 表示も承認済みのみ
});

// ─────────── モデレーション ───────────

it('makes reviews reportable and purges reports on delete', function () {
    expect(Report::REPORTABLE_TYPES)->toHaveKey('review')
        ->and(Report::REPORTABLE_TYPES['review'])->toBe(Review::class);

    $m = rsModel();
    $r = rsReview($m, 4);
    Report::create(['reportable_type' => Review::class, 'reportable_id' => $r->id, 'reason' => 'spam', 'status' => 'open']);

    $r->delete();
    expect(Report::where('reportable_type', Review::class)->count())->toBe(0); // 通報purge
});
