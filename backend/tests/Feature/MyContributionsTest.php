<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\DiscussionThread;
use App\Models\GarageComment;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\Review;
use App\Models\User;

function mcUser(string $handle, string $email): User
{
    return User::factory()->create(['name' => '本名'.$handle, 'review_display_name' => $handle, 'email' => $email]);
}

function mcModel(): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', 'rebel-250')->first()
        ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250', 'displacement' => 250]);
}

function mcReview(User $u, BikeModel $m, string $title): Review
{
    return Review::create(['user_id' => $u->id, 'bike_model_id' => $m->id, 'title' => $title, 'body' => '本文'.$title, 'is_approved' => true]);
}

// ───────── アクセス制御 ─────────

it('requires login to view the contributions hub', function () {
    $this->get(route('mypage.contributions'))->assertRedirect(route('login'));
});

// ───────── 自分の投稿だけ集約 ─────────

it('shows only the current user own contributions across types', function () {
    $me = mcUser('me', 'me@example.com');
    $other = mcUser('other', 'other@example.com');
    $model = mcModel();

    mcReview($me, $model, '自分のレビュー');
    DiscussionThread::create(['bike_model_id' => $model->id, 'user_id' => $me->id, 'type' => 'chat', 'status' => 'published', 'body' => '自分のひとこと']);
    mcReview($other, $model, '他人のレビュー');

    $this->actingAs($me)->get(route('mypage.contributions'))
        ->assertOk()
        ->assertSee('自分のレビュー')
        ->assertSee('自分のひとこと')
        ->assertSee('合計 2 件')          // 件数サマリ（積み上げ）
        ->assertDontSee('他人のレビュー'); // 他人の投稿は出さない
});

it('shows a pending badge for unapproved own reviews and a link to the source page', function () {
    $me = mcUser('me2', 'me2@example.com');
    $model = mcModel();
    Review::create(['user_id' => $me->id, 'bike_model_id' => $model->id, 'title' => '承認前レビュー', 'body' => 'b', 'is_approved' => false]);

    $this->actingAs($me)->get(route('mypage.contributions'))
        ->assertOk()
        ->assertSee('承認待ち')
        ->assertSee($model->seo_url.'#reviews', false); // 元ページへのリンク
});

it('links to saved spots with the count (map pins aggregated by link)', function () {
    $me = mcUser('me3', 'me3@example.com');
    \App\Models\UserSavedSpot::create(['user_id' => $me->id, 'name' => 'スポットA', 'latitude' => 35.0, 'longitude' => 139.0]);

    $this->actingAs($me)->get(route('mypage.contributions'))
        ->assertOk()
        ->assertSee(route('mypage.saved_spots'), false)
        ->assertSee('マップ保存スポット');
});

// ───────── 削除（所有者のみ） ─────────

it('lets the owner delete their own contribution', function () {
    $me = mcUser('me4', 'me4@example.com');
    $review = mcReview($me, mcModel(), '消すレビュー');

    $this->actingAs($me)
        ->delete(route('mypage.contributions.destroy', ['type' => 'review', 'id' => $review->id]))
        ->assertRedirect(route('mypage.contributions'));

    expect(Review::find($review->id))->toBeNull();
});

it('forbids deleting another user contribution (403, row kept)', function () {
    $me = mcUser('me5', 'me5@example.com');
    $other = mcUser('other5', 'other5@example.com');
    $review = mcReview($other, mcModel(), '他人のレビュー');

    $this->actingAs($me)
        ->delete(route('mypage.contributions.destroy', ['type' => 'review', 'id' => $review->id]))
        ->assertForbidden();

    expect(Review::find($review->id))->not->toBeNull();
});

it('rejects an unknown deletable type (404)', function () {
    $me = mcUser('me6', 'me6@example.com');

    $this->actingAs($me)
        ->delete(route('mypage.contributions.destroy', ['type' => 'bogus', 'id' => 1]))
        ->assertNotFound();
});

it('deletes a garage comment through the hub and purges its reports', function () {
    $me = mcUser('me7', 'me7@example.com');
    $model = mcModel();
    $bike = MyBike::create(['user_id' => $me->id, 'bike_model_id' => $model->id, 'name' => 'my', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 10]);
    $comment = GarageComment::create(['my_bike_id' => $bike->id, 'user_id' => $me->id, 'body' => 'x', 'status' => 'published']);
    \App\Models\Report::create(['reportable_type' => GarageComment::class, 'reportable_id' => $comment->id, 'reason' => 'spam', 'status' => 'open']);

    $this->actingAs($me)->delete(route('mypage.contributions.destroy', ['type' => 'garage_comment', 'id' => $comment->id]))->assertRedirect();

    expect(GarageComment::find($comment->id))->toBeNull()
        ->and(\App\Models\Report::where('reportable_type', GarageComment::class)->count())->toBe(0);
});
