<?php

use App\Models\Shop;
use App\Models\ShopSubmission;
use App\Models\User;
use App\Services\Shop\ShopSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function pendingSubmission(array $overrides = []): ShopSubmission
{
    Storage::disk('local')->put('shop-submissions/test.jpg', 'FAKEJPEGBYTES');

    return ShopSubmission::create(array_merge([
        'shop_name' => '写真店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'shop_type' => 'repair_only', 'image_path' => 'shop-submissions/test.jpg',
        'ip_hash' => str_repeat('a', 64), 'status' => 'pending',
    ], $overrides));
}

// ---- B: upload ----

it('stores an uploaded image to the non-public disk, resized, and records image_path', function () {
    $resp = $this->post('/shops/submit', [
        'shop_name' => 'X', 'prefecture' => '東京都', 'city' => '世田谷区', 'fax_number' => '',
        'image' => UploadedFile::fake()->image('photo.jpg', 2400, 1200),
    ]);
    $resp->assertRedirect(route('shops.submit.create'));

    $sub = ShopSubmission::first();
    expect($sub->image_path)->toStartWith('shop-submissions/');
    Storage::disk('local')->assertExists($sub->image_path);   // 非公開
    Storage::disk('public')->assertMissing($sub->image_path); // 公開に無い
    [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($sub->image_path));
    expect(max($w, $h))->toBeLessThanOrEqual(1600); // 長辺リサイズ
});

it('rejects an oversized image and a non-image file', function () {
    $this->post('/shops/submit', ['shop_name' => 'X', 'prefecture' => '東京都', 'city' => '世田谷区', 'fax_number' => '',
        'image' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg')]) // 6MB
        ->assertSessionHasErrors('image');
    $this->post('/shops/submit', ['shop_name' => 'X', 'prefecture' => '東京都', 'city' => '世田谷区', 'fax_number' => '',
        'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('image');
    expect(ShopSubmission::count())->toBe(0);
});

// ---- D: approve / merge / reject file handling ----

it('approve promotes the image to public and sets local_image_path; pending file removed', function () {
    $sub = pendingSubmission(['address' => null]);
    $shop = app(ShopSubmissionService::class)->approveAsNew($sub);

    expect($shop->local_image_path)->toBe('shop-user/test.jpg');
    Storage::disk('public')->assertExists('shop-user/test.jpg');
    Storage::disk('local')->assertMissing('shop-submissions/test.jpg'); // 孤児なし
    expect($shop->display_image_url)->toContain('shop-user/test.jpg');   // E: 表示規約に乗る
});

it('merge fills image only when the existing shop has none; otherwise deletes the pending file', function () {
    $noImg = Shop::create(['name' => '画像なし', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'a', 'shop_type' => 'dealer', 'source' => 'scraper']);
    $hasImg = Shop::create(['name' => '画像あり', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'b', 'local_image_path' => 'shops/existing.jpg', 'shop_type' => 'dealer', 'source' => 'scraper']);

    app(ShopSubmissionService::class)->mergeInto(pendingSubmission(), $noImg);
    expect($noImg->fresh()->local_image_path)->toBe('shop-user/test.jpg');
    Storage::disk('public')->assertExists('shop-user/test.jpg');

    app(ShopSubmissionService::class)->mergeInto(pendingSubmission(), $hasImg);
    expect($hasImg->fresh()->local_image_path)->toBe('shops/existing.jpg');   // 既存維持
    Storage::disk('local')->assertMissing('shop-submissions/test.jpg');        // 投稿画像は破棄
});

it('reject deletes the pending image file', function () {
    $sub = pendingSubmission();
    app(ShopSubmissionService::class)->reject($sub);
    Storage::disk('local')->assertMissing('shop-submissions/test.jpg');
    expect($sub->fresh()->status)->toBe('rejected');
});

it('an image-less submission flows through approval unchanged (regression)', function () {
    $sub = ShopSubmission::create(['shop_name' => 'noimg', 'prefecture' => '東京都', 'city' => '世田谷区', 'shop_type' => 'repair_only', 'address' => null, 'image_path' => null, 'ip_hash' => str_repeat('a', 64), 'status' => 'pending']);
    $shop = app(ShopSubmissionService::class)->approveAsNew($sub);
    expect($shop->local_image_path)->toBeNull()
        ->and($shop->display_image_url)->toBeNull();
});

// ---- C: preview route permissions ----

it('preview route: admin 200, non-admin 404, guest 404', function () {
    $sub = pendingSubmission();
    $url = route('admin.shop-submission.image', $sub);

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)->assertStatus(200);
    $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertStatus(404);
    auth()->logout();
    $this->get($url)->assertStatus(404);
});
