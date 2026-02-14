/**
 * MotoHub UI Interaction Logic
 * ナビゲーション、サイドバー、お気に入り・比較ボタン等の操作をまとめて制御します
 */
(function() {
    // --- トースト通知機能 ---
    const showToast = (message, type = 'success') => {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-gray-900' : 'bg-red-600';
        const icon = type === 'success' ? 
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : 
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';

        toast.className = `${bgColor} text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0 pointer-events-auto min-w-[200px]`;
        toast.innerHTML = `<div class="shrink-0 text-white">${icon}</div><span class="text-xs font-bold">${message}</span>`;

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    const setupInteractions = () => {
        // --- A. ナビゲーション制御 ---
        try {
            const toggle = document.getElementById('mobile-nav-search-toggle');
            const bar = document.getElementById('mobile-nav-search-bar');
            const input = document.getElementById('mobile-nav-search-input');

            if (toggle && bar) {
                toggle.onclick = (e) => {
                    e.preventDefault(); e.stopPropagation();
                    const isHidden = bar.classList.toggle('hidden');
                    if (!isHidden && input) setTimeout(() => input.focus(), 100);
                };
                document.addEventListener('click', (e) => {
                    if (!bar.classList.contains('hidden') && !bar.contains(e.target) && !toggle.contains(e.target)) {
                        bar.classList.add('hidden');
                    }
                });
            }
        } catch (e) { console.error("Nav Error:", e); }

        // --- B. サイドバー制御 ---
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

        // --- C. モバイル件数更新 ---
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
                form.querySelectorAll('input[type="range"]').forEach(input => input.oninput = updateCount);
            }
        } catch (e) { console.error("Count Update Error:", e); }

        // --- D. お気に入り・比較ボタンの制御 ---
        try {
            document.body.addEventListener('click', (e) => {
                // 1. お気に入りボタン
                const wishlistBtn = e.target.closest('.wishlist-btn');
                if (wishlistBtn) {
                    e.preventDefault(); e.stopPropagation();
                    const id = wishlistBtn.dataset.id;
                    if (id && window.WishlistManager) {
                        wishlistBtn.classList.add('scale-125');
                        setTimeout(() => wishlistBtn.classList.remove('scale-125'), 200);
                        
                        const isAdded = !window.WishlistManager.items.has(parseInt(id));
                        window.WishlistManager.toggle(id);
                        
                        showToast(isAdded ? 'お気に入りに追加しました' : 'お気に入りから削除しました', isAdded ? 'success' : 'error');
                    }
                    return;
                }

                // 2. 比較ボタン
                const compareBtn = e.target.closest('.compare-btn');
                if (compareBtn) {
                    e.preventDefault(); e.stopPropagation();
                    const id = parseInt(compareBtn.dataset.id);
                    
                    // ★修正: CompareManagerが存在するか確認
                    if (id && window.CompareManager) {
                        compareBtn.classList.add('scale-125');
                        setTimeout(() => compareBtn.classList.remove('scale-125'), 200);
                        
                        // ★修正: toggleメソッドを呼び出し、戻り値で判定する
                        const result = window.CompareManager.toggle(id);
                        
                        if (result.success) {
                            if (result.action === 'added') {
                                showToast('比較リストに追加しました');
                            } else {
                                showToast('比較リストから削除しました', 'error');
                            }
                        } else {
                            // 最大数オーバーなどのエラー
                            showToast(result.message, 'error');
                        }
                    } else {
                        console.warn('CompareManager is not loaded.');
                    }
                }
            });
        } catch (e) { console.error("Interaction Error:", e); }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupInteractions);
    } else {
        setupInteractions();
    }
})();