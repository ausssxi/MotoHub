<?php

// 概要タブのレビューダイジェストに「レビューを書く」導線を追加（質問「質問する」と対称）。
// model_detail はSQLite描画不可のため静的ガードで検証する（既存 UgcEmptyStateNudgeTest 参照）。

function ctaBlade(): string
{
    return file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
}

it('shows the review write CTA in the digest when reviews exist (populated state)', function () {
    $b = ctaBlade();

    // レビューがある時も出す導線（$digestReviewCount > 0 のガード付き）
    expect($b)->toContain('@if($digestReviewCount > 0)')
        ->toContain('レビューを書く');

    // populated 用CTAは #review-form へアンカー遷移（質問する の #questions と同種の挙動）
    $afterEndforelse = substr($b, strpos($b, '@endforelse'));
    expect($afterEndforelse)->toContain('href="#review-form"');
});

it('keeps the write CTA in the zero-review empty state too (always present)', function () {
    $b = ctaBlade();

    // 「レビューを書く」は empty(0件) と populated(>0件) の両方にある＝常に出る
    expect(substr_count($b, 'レビューを書く'))->toBeGreaterThanOrEqual(2);
    expect($b)->toContain('最初のオーナーレビューを書きませんか？'); // 呼び水は不変
});

it('uses the same button classes as the 質問する CTA (blue, build-safe)', function () {
    $b = ctaBlade();

    // 質問する と同一クラス（bg-blue-600 / hover:bg-blue-700 / rounded-full）を流用
    expect($b)->toContain('bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700');
    // 旧・レビュー書くボタンの bg-black は残っていない（青に統一）
    // ダイジェストは概要パネル内（overview開始の後）へ移動したため、終端は次タブ(market)を基準にする。
    $digest = substr($b, strpos($b, 'オーナーの声 ダイジェスト'), strpos($b, 'id="tab-panel-market"') - strpos($b, 'オーナーの声 ダイジェスト'));
    expect($digest)->not->toContain('bg-black');
});

it('leaves the discussion digest CTA and the review form itself unchanged', function () {
    $b = ctaBlade();

    // クチコミ・相談側「質問する」CTAは統合スレッド(#threads)へ（bg-blue-600 は共通）
    expect($b)->toContain('質問する')
        ->toContain('href="#threads"');

    // レビューフォーム本体・投稿ボタンは不変
    expect($b)->toContain('id="review-form"')
        ->toContain('id="review-form-element"')
        ->toContain('すべて見る'); // ヘッダーの「すべて見る」も併存
});
