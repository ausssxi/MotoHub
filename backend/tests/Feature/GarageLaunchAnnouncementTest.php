<?php

declare(strict_types=1);

use App\Mail\GarageLaunchAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('renders the announcement with name and key copy', function () {
    $user = User::factory()->create(['name' => '内田', 'email' => 'a@example.com']);
    $rendered = (new GarageLaunchAnnouncement($user))->render();

    expect($rendered)->toContain('内田さん')
        ->toContain('愛車ガレージ')
        ->toContain('あなたも愛車を記録しよう（無料で始める）')
        ->toContain('utm_source=email&utm_medium=announcement&utm_campaign=garage_launch')
        ->toContain('ご返信いただければ'); // 返信での配信停止/感想導線
});

it('falls back to ライダー when name is empty (existing email greeting convention)', function () {
    $user = User::factory()->make(['name' => '', 'email' => 'b@example.com']);
    $rendered = (new GarageLaunchAnnouncement($user))->render();

    expect($rendered)->toContain('ライダーさん');
});

it('has the expected subject', function () {
    $user = User::factory()->make(['name' => 'x']);
    expect((new GarageLaunchAnnouncement($user))->envelope()->subject)->toContain('愛車ガレージ');
});

it('test option sends exactly one mail to the given address', function () {
    Mail::fake();
    User::factory()->count(3)->create();

    $this->artisan('mail:garage-launch', ['--test' => 'me@example.com'])->assertSuccessful();

    Mail::assertSent(GarageLaunchAnnouncement::class, 1);
    Mail::assertSent(GarageLaunchAnnouncement::class, fn ($m) => $m->hasTo('me@example.com'));
});

it('dry-run sends nothing', function () {
    Mail::fake();
    User::factory()->count(5)->create();

    $this->artisan('mail:garage-launch', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingSent();
});

it('does NOT send on full run without confirmation (safety)', function () {
    Mail::fake();
    User::factory()->count(5)->create();

    // 対話確認に「いいえ」＝送らない
    $this->artisan('mail:garage-launch')->expectsConfirmation('本送信します。全 5 名へ告知メールを送りますか？', 'no')->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends to all users with email on confirmed/forced full run', function () {
    Mail::fake();
    User::factory()->count(4)->create();

    $this->artisan('mail:garage-launch', ['--force' => true])->assertSuccessful();

    Mail::assertSent(GarageLaunchAnnouncement::class, 4);
});
