/**
 * MotoHub Seamless Navigation
 * 検索結果から遷移してきた場合に、「前へ」「次へ」「一覧へ戻る」UIを制御します。
 */
document.addEventListener('DOMContentLoaded', () => {
    // グローバル変数 window.currentListingId が定義されている詳細ページのみで動作
    if (typeof window.currentListingId !== 'undefined') {
        const stateStr = sessionStorage.getItem('motohub_search_state');
        
        if (stateStr) {
            try {
                const state = JSON.parse(stateStr);
                const currentId = parseInt(window.currentListingId);
                const currentIndex = state.ids.indexOf(currentId);
                
                // 検索結果のリストに自分が含まれている場合のみナビゲーションを表示
                if (currentIndex !== -1) {
                    const navBar = document.getElementById('search-nav-bar');
                    if(navBar) navBar.classList.remove('hidden');
                    
                    // 一覧へ戻る
                    if(state.listUrl) {
                        const backListBtn = document.getElementById('nav-back-list');
                        if(backListBtn) backListBtn.href = state.listUrl;
                    }
                    
                    // 前の車両へ
                    const prevBtn = document.getElementById('nav-prev-bike');
                    if (prevBtn && currentIndex > 0) {
                        prevBtn.href = '/bikes/' + state.ids[currentIndex - 1];
                        prevBtn.classList.remove('text-gray-600', 'pointer-events-none');
                        prevBtn.classList.add('text-white', 'hover:text-blue-400');
                    }
                    
                    // 次の車両へ
                    const nextBtn = document.getElementById('nav-next-bike');
                    if (nextBtn && currentIndex < state.ids.length - 1) {
                        nextBtn.href = '/bikes/' + state.ids[currentIndex + 1];
                        nextBtn.classList.remove('text-gray-600', 'pointer-events-none');
                        nextBtn.classList.add('text-white', 'hover:text-blue-400');
                    }
                }
            } catch(e) {
                console.error('Seamless nav state parsing error:', e);
            }
        }
    }
});