/**
 * MotoHub Infinite Scroll / Load More Logic (Final Robust Version)
 */
document.addEventListener('DOMContentLoaded', () => {
    const loadMoreBtn = document.getElementById('load-more-btn');
    const container = document.getElementById('load-more-container');
    const grid = document.getElementById('results-grid');
    
    if (!loadMoreBtn || !grid) return;

    console.log('🚀 Infinite Scroll: System Ready.');

    loadMoreBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        // 二重クリック防止
        if (loadMoreBtn.disabled) return;

        // 1. 最新のURLの取得
        let nextUrl = loadMoreBtn.getAttribute('data-next-url');
        if (!nextUrl || nextUrl === 'null' || nextUrl === '') {
            console.log('🏁 No more pages to load.');
            if (container) container.classList.add('hidden');
            return;
        }

        // HTMLエンティティデコード
        const decoder = document.createElement('textarea');
        decoder.innerHTML = nextUrl;
        nextUrl = decoder.value;

        console.log('📡 Click detected. Target URL:', nextUrl);

        // 2. UI要素の取得（Lucideによる置換を考慮して毎回取得）
        const textSpan = loadMoreBtn.querySelector('#load-more-text');
        // IDまたはLucideが生成したクラス名で検索
        const icon = loadMoreBtn.querySelector('#load-more-icon') || loadMoreBtn.querySelector('.lucide-chevron-down');
        const spinner = loadMoreBtn.querySelector('#load-more-spinner') || loadMoreBtn.querySelector('.lucide-loader-2');
        
        // 状態を「読み込み中」へ
        loadMoreBtn.disabled = true;
        if (textSpan) textSpan.textContent = '読み込み中...';
        if (icon) icon.classList.add('hidden');
        if (spinner) spinner.classList.remove('hidden');

        try {
            // 3. 通信用のURL構築（クリーンアップ済み）
            const url = new URL(nextUrl, window.location.origin);
            
            // パラメータを整理
            url.searchParams.delete('load_more');
            url.searchParams.delete('_t');
            url.searchParams.set('load_more', '1');
            url.searchParams.set('_t', Date.now()); // 常に新しいタイムスタンプ

            const fetchUrl = url.toString();
            console.log('🌐 Fetching started:', fetchUrl);

            // 4. 通信実行
            const response = await fetch(fetchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            console.log('📥 Response status:', response.status);

            if (!response.ok) {
                throw new Error(`Server returned ${response.status}`);
            }

            const data = await response.json();

            // 5. HTMLデータの反映
            if (data.html && data.html.trim() !== '') {
                grid.insertAdjacentHTML('beforeend', data.html);
                console.log('✨ HTML appended to grid.');
                
                // アイコンの再描画
                if (window.lucide) window.lucide.createIcons();
                
                // お気に入りボタンの同期
                if (typeof WishlistManager !== 'undefined') {
                    WishlistManager.updateUI();
                }
            }

            // 6. 【重要】Next URLの保存とクリーンアップ
            if (data.next_url) {
                // サーバーから返ったURLから load_more や _t を取り除いてから保存
                const nextCleanUrl = new URL(data.next_url, window.location.origin);
                nextCleanUrl.searchParams.delete('load_more');
                nextCleanUrl.searchParams.delete('_t');
                
                const finalNextUrl = nextCleanUrl.toString();
                loadMoreBtn.setAttribute('data-next-url', finalNextUrl);
                console.log('✅ Clean Next URL saved:', finalNextUrl);
            } else {
                console.log('🏁 Reached the end of results.');
                if (container) container.classList.add('hidden');
                loadMoreBtn.setAttribute('data-next-url', '');
            }

            // 7. SessionStorage同期
            updateSearchState(grid);

        } catch (error) {
            console.error('❌ Infinite Scroll Error:', error);
            alert('車両の読み込みに失敗しました。');
        } finally {
            // 8. UIの復元（最新の要素を再取得して確実に操作）
            loadMoreBtn.disabled = false;
            const currentText = loadMoreBtn.querySelector('#load-more-text');
            // Lucide描画後のSVG要素も考慮
            const currentIcon = loadMoreBtn.querySelector('.lucide-chevron-down') || loadMoreBtn.querySelector('#load-more-icon');
            const currentSpinner = loadMoreBtn.querySelector('.lucide-loader-2') || loadMoreBtn.querySelector('#load-more-spinner');

            if (currentText) currentText.textContent = 'さらに車両を見る';
            if (currentIcon) currentIcon.classList.remove('hidden');
            if (currentSpinner) currentSpinner.classList.add('hidden');
            
            console.log('🔄 UI Restored. Ready for next click.');
        }
    });

    function updateSearchState(gridElement) {
        const stateStr = sessionStorage.getItem('motohub_search_state');
        if (stateStr) {
            try {
                const state = JSON.parse(stateStr);
                const cards = gridElement.querySelectorAll('.compare-btn');
                const newIds = Array.from(cards)
                    .map(btn => parseInt(btn.dataset.id))
                    .filter(id => !isNaN(id));
                
                state.ids = newIds;
                sessionStorage.setItem('motohub_search_state', JSON.stringify(state));
                console.log('💾 Storage updated. Total IDs:', newIds.length);
            } catch (e) {
                console.error('Storage sync error:', e);
            }
        }
    }
});