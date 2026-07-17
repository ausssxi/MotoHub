<?php

declare(strict_types=1);

use App\Mail\NewFeaturesAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * 新機能告知メールの一斉配信コマンド。
 * ★最重要: 1人ずつ個別送信（1通=1宛先）。複数アドレスを To/CC にまとめない＝アドレス相互漏洩の防止。
 */
it('sends one individual email per recipient — never batches addresses into one To/CC', function () {
    Mail::fake();
    $a = User::factory()->create(['email' => 'a@example.com']);
    $b = User::factory()->create(['email' => 'b@example.com']);
    $c = User::factory()->create(['email' => 'c@example.com']);

    $this->artisan('mail:new-features --force')->assertSuccessful();

    // 送信数＝会員数（まとめ送りしていない）
    Mail::assertSent(NewFeaturesAnnouncement::class, 3);

    // ★各メールの宛先は「本人1人のみ」＝他会員のアドレスが同じ通に混ざらない
    foreach ([$a, $b, $c] as $u) {
        Mail::assertSent(NewFeaturesAnnouncement::class, fn ($m) => $m->hasTo($u->email) && count($m->to) === 1);
    }
});

it('excludes users without an email address', function () {
    Mail::fake();
    User::factory()->create(['email' => 'has@example.com']);
    User::factory()->create(['email' => '']); // 空メールは対象外

    $this->artisan('mail:new-features --force')->assertSuccessful();

    Mail::assertSent(NewFeaturesAnnouncement::class, 1);
    Mail::assertSent(NewFeaturesAnnouncement::class, fn ($m) => $m->hasTo('has@example.com'));
});

it('--test sends exactly one email to the given address only', function () {
    Mail::fake();
    User::factory()->create(['email' => 'member@example.com']);

    $this->artisan('mail:new-features --test=me@example.com')->assertSuccessful();

    Mail::assertSent(NewFeaturesAnnouncement::class, 1);
    Mail::assertSent(NewFeaturesAnnouncement::class, fn ($m) => $m->hasTo('me@example.com'));
    Mail::assertNotSent(NewFeaturesAnnouncement::class, fn ($m) => $m->hasTo('member@example.com'));
});

it('--dry-run sends nothing', function () {
    Mail::fake();
    User::factory()->count(3)->create();

    $this->artisan('mail:new-features --dry-run')->assertSuccessful();

    Mail::assertNothingSent();
});

it('the mailable renders with the subject and the real feature URLs', function () {
    $user = User::factory()->create(['name' => '本名タロウ', 'email' => 'r@example.com']);
    $mailable = new NewFeaturesAnnouncement($user);

    $mailable->assertHasSubject('【MotoHub】新機能を追加しました（プロフィールアイコン・愛車ガレージ）');

    $html = $mailable->render();
    expect($html)->toContain(route('profile.edit'))
        ->toContain(route('mybikes.index'))
        ->toContain(route('mypage.contributions'))
        ->toContain('本名タロウさん')            // 宛名
        ->toContain('ご返信いただけると嬉しい'); // 返信ベースの配信停止導線
});
