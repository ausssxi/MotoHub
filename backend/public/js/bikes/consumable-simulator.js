/**
 * MotoHub 消耗品コスト（走行距離）シミュレーター
 *
 * サーバー側（show.blade.php）で4距離ぶんの「整形済み金額（カンマ区切り文字列）」を
 * data-km-data(JSON) に埋め込み、走行距離セレクタの変更で表示を差し替えるだけにする。
 * 通信はしない（金額はページ表示時点で確定）。JS側で数値整形をやり直さない（表示ブレ防止）。
 * loan-simulator.js と同じ素のJS作法（Alpine.js は使わない）。
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('consumable-sim');
    if (!container) return; // 消耗品データが無い車種では第2段そのものが無い

    const select = document.getElementById('consumable-km');
    if (!select) return;

    let kmData;
    try {
        kmData = JSON.parse(container.dataset.kmData || '{}');
    } catch (e) {
        return; // 壊れたJSONなら初期表示のまま（サーバー描画済み）にする
    }

    const subtotalEl = document.getElementById('consumable-subtotal');
    const grandEl = document.getElementById('maintenance-grand-total');

    function render() {
        const data = kmData[select.value];
        if (!data) return;

        // 各消耗品の金額（available な項目のみ data.items にキーがある）
        const items = data.items || {};
        Object.keys(items).forEach((key) => {
            const el = document.getElementById('consumable-cost-' + key);
            if (el) el.textContent = items[key];
        });

        if (subtotalEl) subtotalEl.textContent = data.subtotal;
        if (grandEl) grandEl.textContent = data.grand;
    }

    select.addEventListener('change', render);

    // 初期表示（既定選択に合わせる）
    render();
});
