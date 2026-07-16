<?php

declare(strict_types=1);

use App\Models\MyBike;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * プロフィールアバター（アップロード・表示・通報・モデレーション）。
 * ★privacy: 公開面は avatar_url＋ハンドルのみ。本名/メール/連番idは出さない。
 */
function avatarUser(string $handle = 'rider_x', string $email = 'av@example.com'): User
{
    return User::factory()->create(['name' => '本名タロウ', 'review_display_name' => $handle, 'email' => $email]);
}

// ---- アップロード（リサイズ→EXIF除去→public保存→カラム更新） ----

it('uploads an avatar: resizes to a square jpeg on the public disk and sets avatar_path', function () {
    Storage::fake('public');
    $user = avatarUser();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 1000, 400), // 非正方形
        ])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);

    // 再エンコードで正方形(512)・JPEG化されている＝EXIF/GPSはこの再エンコードで除去される
    $info = getimagesizefromstring(Storage::disk('public')->get($user->avatar_path));
    expect($info[0])->toBe(512)          // width
        ->and($info[1])->toBe(512)       // height
        ->and($info[2])->toBe(IMAGETYPE_JPEG);
});

it('rejects a non-image file disguised or otherwise (real MIME check)', function () {
    Storage::fake('public');
    $user = avatarUser();

    // 実行可能ファイル等を .jpg 偽装しても finfo 実体判定で弾く
    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('evil.jpg', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

it('rejects an oversized image', function () {
    Storage::fake('public');
    $user = avatarUser();
    $tooBig = (int) config('avatar.max_upload_kb') + 1024;

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('huge.jpg', $tooBig, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

it('deletes the old file when the avatar is replaced (no storage garbage)', function () {
    Storage::fake('public');
    $user = avatarUser();

    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $user->refresh()->avatar_path;
    Storage::disk('public')->assertExists($old);

    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('b.jpg')]);
    $new = $user->refresh()->avatar_path;

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);   // 旧ファイルは消えている
    Storage::disk('public')->assertExists($new);
});

it('removes the avatar: clears the column and deletes the file', function () {
    Storage::fake('public');
    $user = avatarUser();
    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $path = $user->refresh()->avatar_path;

    $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

// ---- avatar_url アクセサの優先順位（自前 > OAuth > null） ----

it('avatar_url prefers the uploaded path, then the OAuth url, else null', function () {
    Storage::fake('public');

    $none = avatarUser('h1', 'h1@example.com');
    expect($none->avatar_url)->toBeNull();  // 未設定＝デフォルト表示にフォールバック

    $oauth = avatarUser('h2', 'h2@example.com');
    $oauth->update(['avatar' => 'https://oauth.example/pic.png']);
    expect($oauth->avatar_url)->toBe('https://oauth.example/pic.png'); // OAuth URL

    $this->actingAs($oauth)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $oauth->refresh();
    // 自前アップロードが OAuth より優先される
    expect($oauth->avatar_url)->toContain($oauth->avatar_path)
        ->and($oauth->avatar_url)->not->toBe('https://oauth.example/pic.png');
});

// ---- 表示（公開プロフィール） ----

it('shows the avatar on the public profile and never leaks real name or email', function () {
    Storage::fake('public');
    $user = avatarUser('rider_x', 'leak@example.com');
    $token = $user->ensurePublicToken();
    MyBike::create(['user_id' => $user->id, 'name' => '公開号', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 1]);
    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();

    expect($html)->toContain($user->refresh()->avatar_path) // アバターURLが出る
        ->toContain('rider_x')
        ->not->toContain('本名タロウ')
        ->not->toContain('leak@example.com');
});

// ---- 通報（public_token で解決・連番id を DOM に出さない） ----

it('reports an avatar via public_token (no sequential user id in the form)', function () {
    $user = avatarUser();
    $token = $user->ensurePublicToken();
    MyBike::create(['user_id' => $user->id, 'name' => '公開号', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 1]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    // 通報フォームは token を送る（連番 user id の hidden input は存在しない）
    expect($html)->toContain('value="user_avatar"')
        ->toContain('name="token" value="'.$token.'"')
        ->not->toContain('name="id" value="'.$user->id.'"');

    // token 経由で通報すると User を指す Report が作られる（reportable_id は連番だが DB内部・DOM非露出）
    $this->post(route('reports.store'), ['type' => 'user_avatar', 'token' => $token, 'reason' => 'defame'])
        ->assertSessionHas('report_success');

    $this->assertDatabaseHas('reports', [
        'reportable_type' => User::class,
        'reportable_id' => $user->id,
        'reason' => 'defame',
    ]);
});

it('avatar report with an unknown token is soft-handled (no row, no disclosure)', function () {
    $this->post(route('reports.store'), ['type' => 'user_avatar', 'token' => 'doesnotexist'])
        ->assertSessionHas('report_success');

    expect(Report::where('reportable_type', User::class)->count())->toBe(0);
});

// ---- モデレーション（User削除で通報をpurge＝孤児を残さない） ----

it('purges avatar reports when the user is deleted', function () {
    $user = avatarUser();
    Report::create(['reportable_type' => User::class, 'reportable_id' => $user->id, 'reason' => 'spam', 'status' => Report::STATUS_OPEN]);
    expect(Report::where('reportable_id', $user->id)->count())->toBe(1);

    $user->delete();

    expect(Report::where('reportable_type', User::class)->where('reportable_id', $user->id)->count())->toBe(0);
});
