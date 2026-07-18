<?php

declare(strict_types=1);

use App\Models\TroubleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const RESCUE_SID = '11111111-2222-3333-4444-555555555555';

// ---- 計測（PIIフリー・既存 pass-through に乗る） ----

it('accepts battery_rescue as a tracked cta token (rides the existing PII-free pass-through)', function () {
    expect(TroubleEvent::CTAS)->toContain('battery_rescue');

    $this->post('/trouble/track', [
        'session_id' => RESCUE_SID, 'event' => 'cta_clicked',
        'card' => 'battery', 'verdict' => 'diy', 'cta' => 'battery_rescue', 'ref' => 'blog_batt',
    ])->assertNoContent();

    $row = TroubleEvent::sole();
    expect($row->cta)->toBe('battery_rescue')
        ->and($row->card)->toBe('battery')
        ->and($row->ref)->toBe('blog_batt'); // ref pass-through 維持
});

// ---- URLゲート（未設定＝非表示・偽ボタンを出さない） ----

it('exposes an empty rescue url by default so the CTA stays hidden (no affiliate url leaks)', function () {
    config(['diagnosis.battery_rescue.url' => '']);
    $html = $this->get(route('trouble.index'))->assertOk()->getContent();

    // クライアント側ゲート用データが空url（＝x-if の && batteryRescue.url が false）
    expect($html)->toContain('window.__batteryRescue')
        ->toContain('"url":""')
        ->not->toContain('example.com'); // 実URLはページ内に一切出ない
});

it('injects the affiliate url (with PR + sponsored rel markup) only when configured', function () {
    config(['diagnosis.battery_rescue.url' => 'https://example.com/rescue']);
    $html = $this->get(route('trouble.index'))->assertOk()->getContent();

    expect($html)->toContain('example.com')                      // 設定時のみURLが載る（ゲート）
        ->toContain('rel="nofollow sponsored noopener"')          // 景表法/SEO（ブロック markup）
        ->toContain('battery_rescue')                            // trackCta トークン
        ->toContain('>PR<');                                      // PR表記
});

// ---- バッテリーカードのみに限定（他カードに出ない） ----

it('gates the rescue CTA to battery cards only (fitment_task === battery)', function () {
    config(['diagnosis.battery_rescue.url' => 'https://example.com/rescue']);
    $html = $this->get(route('trouble.index'))->assertOk()->getContent();

    // x-if がバッテリー系カード（fitment_task='battery'）に限定されている
    expect($html)->toContain("card?.fitment_task === 'battery' && batteryRescue.url");
});

// ---- 既存の安全文言・商品リンク・診断を壊さない（回帰） ----

it('keeps the existing safety wording and the product (parts) link intact', function () {
    // 安全文言（config の advice 群）を壊していない＝config を直接検証（HTMLは@jsonでunicode escapeされるため）
    $advice = collect(config('diagnosis.cards'))->pluck('advice')->filter()->implode(' ');
    expect($advice)->toContain('無理'); // 「無理をせず／無理に走らない」等の安全配慮文言

    // 既存の商品CTA（自分で交換）定義が残っている（救援を足しても商品リンクを壊していない）
    expect(config('diagnosis.cards.battery.parts_label'))->toBe('バッテリーの価格を比較する');

    // ページに商品CTAの導線markupが残っている
    $html = $this->get(route('trouble.index'))->assertOk()->getContent();
    expect($html)->toContain("trackCta('parts')");
});
