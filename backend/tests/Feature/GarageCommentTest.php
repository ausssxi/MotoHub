<?php

declare(strict_types=1);

use App\Jobs\SendGarageActivityNotification;
use App\Models\BikeModel;
use App\Models\GarageComment;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(fn () => config()->set('ng_words.words', []));

function gcUser(string $handle, string $email): User
{
    return User::factory()->create(['name' => '本名', 'review_display_name' => $handle, 'email' => $email]);
}

function gcBike(User $owner, bool $public = true): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $owner->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

// ───────── 投稿（会員限定） ─────────

it('lets a member comment on a public garage (published, immediate)', function () {
    $owner = gcUser('owner', 'o@example.com');
    $commenter = gcUser('commenter', 'c@example.com');
    $bike = gcBike($owner);

    $this->actingAs($commenter)
        ->post(route('garage.comment', $bike->id), ['body' => 'かっこいいですね！'])
        ->assertRedirect();

    $comment = GarageComment::first();
    expect($comment)->not->toBeNull()
        ->and($comment->my_bike_id)->toBe($bike->id)
        ->and($comment->user_id)->toBe($commenter->id)
        ->and($comment->body)->toBe('かっこいいですね！')
        ->and($comment->status)->toBe('published');
});

it('requires login to comment (guest redirected)', function () {
    $bike = gcBike(gcUser('owner2', 'o2@example.com'));

    $this->post(route('garage.comment', $bike->id), ['body' => 'x'])->assertRedirect(route('login'));
    expect(GarageComment::count())->toBe(0);
});

it('cannot comment on a private garage (404)', function () {
    $owner = gcUser('owner3', 'o3@example.com');
    $commenter = gcUser('commenter3', 'c3@example.com');
    $bike = gcBike($owner, public: false);

    $this->actingAs($commenter)->post(route('garage.comment', $bike->id), ['body' => 'x'])->assertNotFound();
    expect(GarageComment::count())->toBe(0);
});

it('rejects an empty comment and an NG-word comment (null-safe)', function () {
    config()->set('ng_words.words', ['アホ']);
    $commenter = gcUser('commenter4', 'c4@example.com');
    $bike = gcBike(gcUser('owner4', 'o4@example.com'));

    $this->actingAs($commenter)->post(route('garage.comment', $bike->id), ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($commenter)->post(route('garage.comment', $bike->id), ['body' => 'アホみたいに速い'])->assertSessionHasErrors('body');
    expect(GarageComment::count())->toBe(0);
});

// ───────── 通知（再訪フック・自己通知抑制） ─────────

it('notifies the owner when someone else comments', function () {
    Bus::fake();
    $owner = gcUser('owner5', 'o5@example.com');
    $commenter = gcUser('commenter5', 'c5@example.com');
    $bike = gcBike($owner);

    $this->actingAs($commenter)->post(route('garage.comment', $bike->id), ['body' => '素敵']);

    Bus::assertDispatchedAfterResponse(SendGarageActivityNotification::class);
});

it('does not notify when the owner comments on their own garage', function () {
    Bus::fake();
    $owner = gcUser('owner6', 'o6@example.com');
    $bike = gcBike($owner);

    $this->actingAs($owner)->post(route('garage.comment', $bike->id), ['body' => '自分メモ']);

    Bus::assertNotDispatchedAfterResponse(SendGarageActivityNotification::class);
});

it('notifies the owner on a new like but not on un-like', function () {
    Bus::fake();
    $owner = gcUser('owner7', 'o7@example.com');
    $liker = gcUser('liker7', 'l7@example.com');
    $bike = gcBike($owner);

    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))->assertOk(); // いいね追加 → 通知
    Bus::assertDispatchedAfterResponse(SendGarageActivityNotification::class);

    Bus::fake(); // リセット
    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))->assertOk(); // いいね解除 → 通知しない
    Bus::assertNotDispatchedAfterResponse(SendGarageActivityNotification::class);
});

// ───────── モデレーション ─────────

it('registers garage_comment as reportable and purges reports on delete', function () {
    expect(Report::REPORTABLE_TYPES)->toHaveKey('garage_comment')
        ->and(Report::REPORTABLE_TYPES['garage_comment'])->toBe(GarageComment::class);

    $owner = gcUser('owner8', 'o8@example.com');
    $commenter = gcUser('commenter8', 'c8@example.com');
    $bike = gcBike($owner);
    $comment = GarageComment::create(['my_bike_id' => $bike->id, 'user_id' => $commenter->id, 'body' => 'x', 'status' => 'published']);
    Report::create(['reportable_type' => GarageComment::class, 'reportable_id' => $comment->id, 'reason' => 'spam', 'status' => 'open']);

    $comment->delete();

    expect(Report::where('reportable_type', GarageComment::class)->count())->toBe(0); // 通報purge
});

it('accepts a report on a garage comment through the generic endpoint', function () {
    $owner = gcUser('owner9', 'o9@example.com');
    $commenter = gcUser('commenter9', 'c9@example.com');
    $bike = gcBike($owner);
    $comment = GarageComment::create(['my_bike_id' => $bike->id, 'user_id' => $commenter->id, 'body' => 'x', 'status' => 'published']);

    $this->post(route('reports.store'), ['type' => 'garage_comment', 'id' => $comment->id, 'reason' => 'spam'])->assertRedirect();

    expect(Report::where('reportable_type', GarageComment::class)->where('reportable_id', $comment->id)->count())->toBe(1);
});

// ───────── 表示（過疎に見せない・privacy） ─────────

it('shows the comment form for a member and a login prompt for guests', function () {
    $owner = gcUser('owner10', 'o10@example.com');
    $viewer = gcUser('viewer10', 'v10@example.com');
    $bike = gcBike($owner);

    // ゲスト先（actingAs はテスト内で以降のリクエストにも残るため、未ログインを先に検証）
    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('ログインしてこのガレージにコメントする');

    $this->actingAs($viewer)->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('garage/'.$bike->id.'/comments', false); // コメントフォームの action
});

it('renders a posted comment on the public garage without leaking the real name', function () {
    $owner = gcUser('owner11', 'o11@example.com');
    $commenter = gcUser('handleさん', 'c11@example.com');
    $bike = gcBike($owner);
    GarageComment::create(['my_bike_id' => $bike->id, 'user_id' => $commenter->id, 'body' => '通勤に最高ですね', 'status' => 'published']);

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('通勤に最高ですね')
        ->assertSee('handleさん')       // 表示名
        ->assertDontSee('本名');         // 本名は出さない
});
