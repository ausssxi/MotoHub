<?php

// 入荷通知CTAのGA4計測（クリック→許可→購読成功のファネル）。
// 純クライアントJSのためJSランナーは無く、配線を静的ガードで検証（構文は node -c で別途担保）。

function pushManagerJs(): string
{
    return file_get_contents(public_path('js/push-manager.js'));
}

function softPromptJs(): string
{
    return file_get_contents(public_path('js/push-soft-prompt.js'));
}

// ─────────── ファネルの3イベント（push-manager.js） ───────────

it('fires the CTA click, permission result, and subscribe success events', function () {
    $js = pushManagerJs();

    expect($js)->toContain("'notify_cta_click'")          // ファネル先頭：ボタンクリック
        ->toContain("'notify_permission_result'")         // 許可の壁：granted/denied
        ->toContain("'notify_subscribe_success'");        // 到達点：DB保存成功
});

it('tags the CTA click with the touchpoint source (hero/sidebar/market)', function () {
    $js = pushManagerJs();

    // 導線種別を push-area 要素IDから判定
    expect($js)->toContain("'push-area-header'")->toContain("return 'hero'")
        ->toContain("'push-area-sidebar'")->toContain("return 'sidebar'")
        ->toContain('push-area-spread')->toContain("return 'market'");
});

it('sends the permission result value (granted/denied/default) as a param', function () {
    $js = pushManagerJs();

    expect($js)->toContain("track('notify_permission_result', { result: p");
});

// ─────────── ポップアップ導線（push-soft-prompt.js） ───────────

it('fires popup CTA click and dismiss events on the soft prompt', function () {
    $js = softPromptJs();

    expect($js)->toContain("track('notify_cta_click', { source: 'popup'")  // ポップアップのクリック
        ->toContain("track('notify_popup_dismiss'");                       // あとで/×の離脱
});

// ─────────── 非ブロッキング＆回帰 ───────────

it('wraps gtag in a guarded, non-blocking track helper in both files', function () {
    foreach ([pushManagerJs(), softPromptJs()] as $js) {
        // typeof gtag ガード＋try/catch＝イベント送信が失敗しても購読処理を止めない
        expect($js)->toContain("if (typeof gtag === 'function')")
            ->toContain('try { gtag(');
    }
});

it('leaves the existing push_subscribe event and subscribe flow intact', function () {
    $js = pushManagerJs();

    // 既存の購読成功イベントとPOST・許可判定は不変（計測を足しただけ）
    expect($js)->toContain("gtag('event', 'push_subscribe'")
        ->toContain("fetch('/api/push/subscribe'")
        ->toContain("if (p !== 'granted') throw");
});
