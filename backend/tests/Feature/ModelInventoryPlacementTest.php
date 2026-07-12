<?php

// 車種ページ: 相場タブ直後に販売中車両を配置(A) ＋ 比較セクション重複を統合(B)。
// model_detail はSQLite描画不可のため静的ガードで検証（既存 UgcEmptyStateNudgeTest 参照）。

function invBlade(): string
{
    return file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
}

function invCtrl(): string
{
    return file_get_contents(app_path('Http/Controllers/Bike/BikeController.php'));
}

// ─────────── A. 相場直後に販売中車両 ───────────

it('places the listings excerpt inside the market tab, before the inventory tab', function () {
    $b = invBlade();

    $marketStart = strpos($b, 'id="tab-panel-market"');
    $marketEnd = strpos($b, '/tab-panel-market');
    $inventoryStart = strpos($b, 'id="tab-panel-inventory"');
    $excerpt = strpos($b, 'いま販売中の');

    expect($excerpt)->toBeGreaterThan($marketStart)   // market タブ内
        ->and($excerpt)->toBeLessThan($marketEnd)     // market タブ末尾より前
        ->and($excerpt)->toBeLessThan($inventoryStart); // 在庫タブより前＝相場直後
});

it('reuses existing $listings (take 4, no new query) and hides when zero stock', function () {
    $b = invBlade();

    // 既存 $listings を take(4) で流用（追加クエリなし）。0台は @if で非表示＝破綻しない
    expect($b)->toContain('$listings->take(4)')
        ->toContain('@if(count($listings) > 0)');
});

// ─────────── B. 比較セクションの統合 ───────────

it('removes the duplicate comparedPairs compare section from the view', function () {
    $b = invBlade();

    expect($b)->not->toContain('とよく比較される車種') // ②の見出し撤去
        ->not->toContain('$comparedPairs');           // ②の変数参照撤去
});

it('keeps the single canonical compare section and its internal links', function () {
    $b = invBlade();

    expect($b)->toContain('$relatedCompares')                // 残す①
        ->toContain('{{ $model->name }} の比較')             // ①の見出し
        ->toContain("route('bikes.model_compare_hub')");     // 比較ハブへの内部リンク維持
});

it('drops the now-dead comparedPairs computation from the controller', function () {
    $c = invCtrl();

    expect($c)->not->toContain("'comparedPairs'")  // compact から除去
        ->not->toContain('$comparedPairs =');      // 再取得クエリ撤去
});

// ─────────── 回帰: UGCダイジェスト・タブ・アンカー不変 ───────────

it('leaves the UGC digest and tab anchors intact', function () {
    $b = invBlade();

    expect($b)->toContain('オーナーの声 ダイジェスト')  // 昨日のUGCダイジェスト不変
        ->toContain('id="tab-panel-community"')
        ->toContain('id="reviews"')
        ->toContain('id="questions"')
        ->toContain('id="tab-panel-inventory"');       // 在庫タブ本体は維持
});
