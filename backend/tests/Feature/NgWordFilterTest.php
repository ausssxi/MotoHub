<?php

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Services\Moderation\NgWordFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────── フィルタ単体 ───────────

it('detects an ng word (case-insensitive, partial match)', function () {
    $f = new NgWordFilter(['死ね', 'スパム語']);
    expect($f->contains('お前なんか死ねばいい'))->toBeTrue()
        ->and($f->contains('これはスパム語を含む'))->toBeTrue();
});

it('passes clean text and legitimate criticism', function () {
    $f = new NgWordFilter(['死ね', '殺す']);
    // 正当な批判は通す（ネガティブ投稿は許容する方針）
    expect($f->contains('対応が遅かった。愛想も悪い'))->toBeFalse()
        ->and($f->contains('値段が高いと感じた'))->toBeFalse()
        ->and($f->contains(''))->toBeFalse()
        ->and($f->contains(null))->toBeFalse();
});

// ─────────── 投稿フローでの適用 ───────────

it('blocks an acceptance comment containing an ng word', function () {
    config()->set('ng_words.words', ['死ね']);

    $shop = Shop::create([
        'name' => 'テスト店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'ng-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
    ]);

    $this->post("/shops/{$shop->id}/acceptance-report", [
        'accepts_bring_in' => '1',
        'comment' => 'こんな店は死ね',
    ])->assertSessionHasErrors('comment');

    expect(ShopAcceptanceReport::count())->toBe(0); // 保存されない
});

it('does not leak the matched ng word in the error message', function () {
    config()->set('ng_words.words', ['死ね']);

    $shop = Shop::create([
        'name' => 'テスト店2', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'ng2-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
    ]);

    $res = $this->post("/shops/{$shop->id}/acceptance-report", [
        'accepts_bring_in' => '1', 'comment' => 'こんな店は死ね',
    ]);

    $errors = session('errors')->get('comment');
    expect(implode(' ', $errors))->not->toContain('死ね'); // ヒット語を露出しない
});

it('allows a legitimate negative comment through the acceptance form', function () {
    config()->set('ng_words.words', ['死ね', '殺す']);

    $shop = Shop::create([
        'name' => 'テスト店3', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'ng3-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
    ]);

    $this->post("/shops/{$shop->id}/acceptance-report", [
        'accepts_bring_in' => '1', 'comment' => '対応が遅くて不満だった',
    ])->assertSessionDoesntHaveErrors('comment');

    expect(ShopAcceptanceReport::where('comment', '対応が遅くて不満だった')->exists())->toBeTrue();
});
