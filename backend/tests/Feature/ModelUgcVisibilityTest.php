<?php

// 車種ページのUGCダイジェスト（投稿導線の前方露出）。
// model_detail はSQLite描画不可（既存 UgcEmptyStateNudgeTest 参照）のため静的ガードで検証する。

function detailBlade(): string
{
    return file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
}

// ─────────── A. ダイジェストの新設・位置 ───────────

it('renders the owner-voice digest before the tab panels (visible without operating tabs)', function () {
    $b = detailBlade();

    expect($b)->toContain('オーナーの声 ダイジェスト')          // 新設セクション
        ->toContain('オーナーレビュー')
        ->toContain('クチコミ・相談');

    // ダイジェストはタブ内容(tab-panel-overview)より手前に置かれている
    expect(strpos($b, 'オーナーの声 ダイジェスト'))
        ->toBeLessThan(strpos($b, 'id="tab-panel-overview"'));
});

it('reuses existing controller variables for the digest (no new query)', function () {
    $b = detailBlade();

    // レビューは既存 $model->reviews、クチコミは統合スレッド $modelThreads を流用（追加取得なし）
    expect($b)->toContain('$digestReviews = $model->reviews->sortByDesc')
        ->toContain('$digestThreads = !empty($modelThreads)')
        ->toContain('$modelThreadsTotal');
});

it('uses only build-safe line-clamp classes in the digest (avoids purged line-clamp-4)', function () {
    $b = detailBlade();

    // ダイジェスト本文は line-clamp-3 / line-clamp-2（ビルド済みに実在）
    expect($b)->toContain('line-clamp-3')
        ->toContain('line-clamp-2');
});

// ─────────── empty state（呼び水） ───────────

it('shows welcoming empty states (nudges) for zero-review and zero-question models', function () {
    $b = detailBlade();

    expect($b)->toContain('最初のオーナーレビューを書きませんか？')                       // レビュー0件の呼び水
        ->toContain('「通勤に最高」などひとことでもOK。質問にはMotoHubがすぐ回答します。') // クチコミ0件の呼び水（casual誘い込み）
        ->toContain('レビューを書く')
        ->toContain('ひとこと・質問する');
});

// ─────────── B. タブ順の変更 ───────────

it('moves the review/FAQ tab button right after 概要 (before 相場)', function () {
    $b = detailBlade();

    // community ボタンが market より前に来る（概要の直後）
    expect(strpos($b, 'data-tab="community"'))
        ->toBeLessThan(strpos($b, 'data-tab="market"'));

    // 全タブボタンは維持
    foreach (['overview', 'community', 'market', 'inventory', 'parts', 'media'] as $tab) {
        expect($b)->toContain('data-tab="'.$tab.'"');
    }
});

// ─────────── C. 回帰: アンカー・既存表示は不変 ───────────

it('keeps tab anchors and IDs intact (deep links still work)', function () {
    $b = detailBlade();

    expect($b)->toContain('id="tab-panel-community"')   // パネルID不変
        ->toContain('id="tab-panel-overview"')
        ->toContain('id="reviews"')                     // #reviews 直リンク先
        ->toContain('id="threads"')                     // #threads 直リンク先（統合スレッド）
        ->toContain('id="review-form"');
});

it('scrolls to the in-panel anchor after switching tabs (1-tap to reviews/threads)', function () {
    $b = detailBlade();

    // タブ内アンカー(#reviews/#threads)は switchTab 後に遅延スクロールで本体へ着地（2タップ解消）
    expect($b)->toContain('switchTab(panelTab);')
        ->toContain("window.scrollTo({ top: y, behavior: 'smooth' })")
        ->toContain('tabNav ? tabNav.offsetHeight'); // sticky タブナビ分オフセット
});

it('switches the Q&A digest to threads and keeps the review section intact', function () {
    $b = detailBlade();

    // クチコミ・相談は統合スレッドへ（旧 approvedAnswers 展開は撤去済み）、レビュー枠は不変
    expect($b)->toContain('id="threads"')
        ->toContain('bikes.thread.store')
        ->toContain('id="model-review-list"')
        ->not->toContain('approvedAnswers->take(2)'); // 旧Q&A展開は撤去
});
