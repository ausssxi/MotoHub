<?php

declare(strict_types=1);

/**
 * ライダーズマップのモバイルUX改善（ボトムシート化＋クラスタリング）。
 * JS挙動（クラスタ展開/集約・スワイプ）は feature test 対象外＝実機確認。
 * ここではページが壊れず、必要アセット/構造マーカーが出力されることを回帰ガードする。
 */
it('renders the riders map with markercluster assets and the mobile bottom-sheet structure', function () {
    $html = $this->get(route('riders.map'))->assertOk()->getContent();

    // マーカークラスタリングのCDN（CSS/JS）が読み込まれている
    expect($html)->toContain('leaflet.markercluster@1.5.3/dist/MarkerCluster.css')
        ->toContain('leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js')
        // アクションをモバイルで地図下へ流す構造（#map-stage 内=地図追従 / #map-actions=下部ツールバー）
        ->toContain('id="map-stage"')
        ->toContain('id="map-actions"')
        // フィルタchipは1行横スクロール
        ->toContain('id="layer-chips"')
        // 既存アクションが残っている（撤去されていない）
        ->toContain('btn-route-toggle')
        ->toContain('btn-current-location')
        // A: 結果ボトムシート（モバイル全画面UI）。件数バー・カードはシート内に維持。
        ->toContain('id="results-sheet"')
        ->toContain('sheet-handle')
        ->toContain('id="result-count"')
        ->toContain('id="result-cards"');
});

it('loads markercluster after leaflet core and before map.js (dependency order)', function () {
    $html = $this->get(route('riders.map'))->assertOk()->getContent();

    $leaflet = strpos($html, 'leaflet@1.9.4/dist/leaflet.js');
    $cluster = strpos($html, 'leaflet.markercluster.js');
    $mapJs = strpos($html, 'js/riders/map.js');

    expect($leaflet)->not->toBeFalse()
        ->and($cluster)->not->toBeFalse()
        ->and($mapJs)->not->toBeFalse()
        ->and($leaflet)->toBeLessThan($cluster)   // leaflet 本体が先
        ->and($cluster)->toBeLessThan($mapJs);    // markercluster は map.js より前
});

// B: /riders-map だけモバイルで bottom-nav を隠す（他ページは維持・ヘッダーは残す）
it('hides the mobile bottom-nav on /riders-map only, keeps it on other pages', function () {
    $map = $this->get(route('riders.map'))->assertOk()->getContent();
    // 非表示CSSがある＝モバイルで bottom-nav を隠す。要素自体はDOMに存在（共通ナビ・ヘッダーは残す）。
    expect($map)->toContain('#bottom-nav { display: none !important; }')
        ->toContain('id="bottom-nav"');

    // 他ページ（保険ハブ）は非表示CSS無し＝bottom-nav 表示のまま（グローバル挙動を壊さない）。
    $other = $this->get(route('hoken'))->assertOk()->getContent();
    expect($other)->toContain('id="bottom-nav"')
        ->not->toContain('#bottom-nav { display: none !important; }');
});
