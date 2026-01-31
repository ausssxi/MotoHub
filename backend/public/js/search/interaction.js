/**
 * MotoHub UI Interaction Logic
 * ナビゲーションの検索窓トグルと、絞り込みサイドバーの両方を安定して制御します。
 */
(function() {
    const setupInteractions = () => {
        // --- A. ナビゲーション：モバイル検索バーのトグル ---
        try {
            const toggle = document.getElementById('mobile-nav-search-toggle');
            const bar = document.getElementById('mobile-nav-search-bar');
            const input = document.getElementById('mobile-nav-search-input');

            if (toggle && bar) {
                toggle.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const isHidden = bar.classList.toggle('hidden');
                    if (!isHidden && input) {
                        setTimeout(() => input.focus(), 100);
                    }
                };

                // 外側をタップしたら閉じる
                document.addEventListener('click', (e) => {
                    if (!bar.classList.contains('hidden') && !bar.contains(e.target) && !toggle.contains(e.target)) {
                        bar.classList.add('hidden');
                    }
                });
            }
        } catch (e) { console.error("Nav Error:", e); }

        // --- B. 検索結果：絞り込みサイドバーの制御 ---
        try {
            const sidebar = document.getElementById('filter-sidebar');
            const openBtn = document.getElementById('open-filter');
            const closeBtn = document.getElementById('close-filter');
            const overlay = document.getElementById('filter-overlay');

            const toggleSidebar = (e) => {
                if (e) e.preventDefault();
                if (!sidebar) return;
                const isActive = sidebar.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            };

            if (openBtn) openBtn.onclick = toggleSidebar;
            if (closeBtn) closeBtn.onclick = toggleSidebar;
            if (overlay) overlay.onclick = toggleSidebar;
        } catch (e) { console.error("Sidebar Error:", e); }

        // --- C. モバイル専用：絞り込み件数の非同期更新 ---
        try {
            const form = document.getElementById('filter-form');
            const mobileHitCount = document.getElementById('mobile-hit-count');

            if (form && mobileHitCount) {
                let timer;
                const updateCount = () => {
                    if (window.innerWidth >= 1024) return;
                    clearTimeout(timer);
                    timer = setTimeout(async () => {
                        const formData = new URLSearchParams(new FormData(form));
                        formData.append('count_only', '1');
                        try {
                            mobileHitCount.innerHTML = '<span class="animate-spin inline-block text-[8px]">⌛</span>';
                            const response = await fetch(`${form.action}?${formData.toString()}`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await response.json();
                            mobileHitCount.textContent = `(${data.total.toLocaleString()}台)`;
                        } catch (err) { mobileHitCount.textContent = '- 台'; }
                    }, 400);
                };

                form.querySelectorAll('input[type="range"]').forEach(input => {
                    input.oninput = updateCount;
                });
            }
        } catch (e) { console.error("Count Update Error:", e); }
    };

    // DOMの準備ができ次第、実行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupInteractions);
    } else {
        setupInteractions();
    }
})();