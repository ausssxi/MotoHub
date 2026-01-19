/**
 * MotoHub Search Page Interaction Logic
 * サイドバー（モバイルモーダル）の制御と、件数の非同期更新を担当します。
 */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('filter-sidebar');
    const openBtn = document.getElementById('open-filter');
    const closeBtn = document.getElementById('close-filter');
    const overlay = document.getElementById('filter-overlay');
    const form = document.getElementById('filter-form');
    const mobileHitCount = document.getElementById('mobile-hit-count');

    /**
     * サイドバー（モバイル用モーダル）の表示切り替え
     */
    const toggle = () => {
        if (!sidebar) return;
        sidebar.classList.toggle('active');
        // モーダルが開いている間は背景のスクロールを禁止
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    };

    if (openBtn) openBtn.addEventListener('click', toggle);
    if (closeBtn) closeBtn.addEventListener('click', toggle);
    if (overlay) overlay.addEventListener('click', toggle);

    /**
     * スマホ画面用：スライダー操作時に件数だけを非同期で更新するロジック
     */
    let updateTimer;
    const updateCountOnly = () => {
        // デスクトップ表示（1024px以上）の場合は即時リロードが行われるため何もしない
        if (window.innerWidth >= 1024) return;
        if (!mobileHitCount || !form) return;

        clearTimeout(updateTimer);
        updateTimer = setTimeout(async () => {
            const formData = new URLSearchParams(new FormData(form));
            formData.append('count_only', '1'); // コントローラー側で件数だけ返すためのフラグ

            try {
                // ローディング表示
                mobileHitCount.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin inline-block"></i>';
                if (window.lucide) window.lucide.createIcons();

                const response = await fetch(`${form.action}?${formData.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                // 取得した件数を反映
                mobileHitCount.textContent = `${data.total.toLocaleString()} 件`;
            } catch (e) {
                console.error('Count update failed', e);
                mobileHitCount.textContent = '- 件';
            }
        }, 300); // 連続操作を考慮し、300ms待機してからリクエストを送信
    };

    // すべてのレンジ入力要素に対して、値が変更されたら件数更新を実行
    if (form) {
        form.querySelectorAll('input[type="range"]').forEach(input => {
            input.addEventListener('input', updateCountOnly);
        });
    }
});