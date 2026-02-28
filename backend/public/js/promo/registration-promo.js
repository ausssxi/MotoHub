/**
 * MotoHub ユーザー登録促進マネージャー
 * 
 * 未ログインユーザーに対して、適切なタイミングで登録を促す3つの施策:
 * 1. お気に入りボタン → 「登録すると保存できます」モーダル
 * 2. 検索結果ページ → 「新着通知をLINEで受け取る」バナー
 * 3. 一定ページ閲覧後 → 「登録しませんか？」ポップアップ
 */
const RegistrationPromo = (() => {
    const STORAGE_KEY = 'motohub_page_views';
    const POPUP_DISMISSED_KEY = 'motohub_popup_dismissed';
    const PAGE_VIEW_THRESHOLD = 5; // 5ページ閲覧後にポップアップ

    let isLoggedIn = false;

    function init(loggedIn) {
        isLoggedIn = loggedIn;
        if (isLoggedIn) return; // ログイン済みなら何もしない

        _trackPageView();
        _initFavoriteIntercept();
        _checkPageViewPopup();
    }

    // ========================================
    // 施策1: お気に入りボタンのインターセプト
    // ========================================
    function _initFavoriteIntercept() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.wishlist-btn');
            if (!btn || isLoggedIn) return;
            
            e.preventDefault();
            e.stopPropagation();
            _showFavoriteModal();
        }, true); // capture phase で先に捕まえる
    }

    function _showFavoriteModal() {
        if (document.getElementById('promo-favorite-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'promo-favorite-modal';
        modal.innerHTML = `
            <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
                <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden transform transition-all" style="animation: promoSlideUp 0.3s ease-out;">
                    
                    <div class="bg-gradient-to-br from-red-500 to-pink-500 p-6 text-center">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </div>
                        <h3 class="text-xl font-black text-white mb-1">お気に入り機能を使おう</h3>
                        <p class="text-sm text-white/80 font-bold">無料登録で気になるバイクを保存できます</p>
                    </div>
                    
                    <div class="p-6 space-y-3">
                        <div class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 w-6 h-6 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 font-black text-xs">✓</span>
                            <span class="text-gray-700 font-bold">お気に入りを保存して<span class="text-red-500">価格比較</span>できる</span>
                        </div>
                        <div class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 w-6 h-6 bg-green-50 rounded-full flex items-center justify-center text-green-500 font-black text-xs">✓</span>
                            <span class="text-gray-700 font-bold"><span class="text-green-600">値下げ通知</span>をLINEやメールで受信</span>
                        </div>
                        <div class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 w-6 h-6 bg-purple-50 rounded-full flex items-center justify-center text-purple-500 font-black text-xs">✓</span>
                            <span class="text-gray-700 font-bold">閲覧履歴で<span class="text-purple-600">見返し</span>が簡単に</span>
                        </div>
                    </div>
                    
                    <div class="px-6 pb-6 space-y-3">
                        <a href="/auth/line/redirect" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-white shadow-sm transition-colors text-sm" style="background-color: #06C755;">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                            LINEで最速かんたん登録
                        </a>
                        <a href="/auth/google/redirect" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-gray-700 border-2 border-gray-200 hover:bg-gray-50 transition-colors text-sm">
                            <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            Googleで登録
                        </a>
                        <a href="/register" class="block text-center text-xs font-bold text-gray-400 hover:text-blue-600 transition-colors">
                            メールアドレスで登録
                        </a>
                    </div>
                    
                    <button onclick="document.getElementById('promo-favorite-modal').remove()" class="absolute top-4 right-4 w-8 h-8 bg-white/20 hover:bg-white/40 rounded-full flex items-center justify-center text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // 背景クリックで閉じる
        modal.querySelector('.fixed').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) modal.remove();
        });
    }

    // ========================================
    // 施策2: 検索結果ページのLINE通知バナー
    // ========================================
    function showSearchBanner(containerId) {
        if (isLoggedIn) return;

        const container = document.getElementById(containerId);
        if (!container) return;

        const banner = document.createElement('div');
        banner.className = 'relative overflow-hidden rounded-2xl border border-green-200 shadow-sm';
        banner.innerHTML = `
            <div class="absolute inset-0 bg-gradient-to-r from-green-50 to-emerald-50"></div>
            <div class="relative p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex items-center gap-3 flex-1">
                    <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #06C755;">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-black text-gray-900">この条件の新着・値下げをLINEでお知らせ</p>
                        <p class="text-[10px] sm:text-xs font-bold text-gray-400 mt-0.5">無料登録でLINE通知を受け取れます</p>
                    </div>
                </div>
                <a href="/auth/line/redirect" class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-black shadow-sm transition-colors active:scale-95" style="background-color: #06C755;">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                    LINE通知を設定
                </a>
            </div>
            <button onclick="this.parentElement.remove()" class="absolute top-2 right-2 w-6 h-6 text-gray-300 hover:text-gray-500 flex items-center justify-center rounded-full transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        container.appendChild(banner);
    }

    // ========================================
    // 施策3: ページ閲覧数に応じたポップアップ
    // ========================================
    function _trackPageView() {
        const views = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10) + 1;
        localStorage.setItem(STORAGE_KEY, views.toString());
    }

    function _checkPageViewPopup() {
        const views = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
        const dismissed = localStorage.getItem(POPUP_DISMISSED_KEY);
        
        // 閾値未満、または過去24時間以内に閉じた場合はスキップ
        if (views < PAGE_VIEW_THRESHOLD) return;
        if (dismissed) {
            const dismissedAt = parseInt(dismissed, 10);
            if (Date.now() - dismissedAt < 24 * 60 * 60 * 1000) return;
        }

        // 3秒後に表示（いきなり出すとウザい）
        setTimeout(() => _showPageViewPopup(views), 3000);
    }

    function _showPageViewPopup(views) {
        if (document.getElementById('promo-pageview-popup')) return;

        const popup = document.createElement('div');
        popup.id = 'promo-pageview-popup';
        popup.innerHTML = `
            <div class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:bottom-6 sm:max-w-sm z-[9998] transform transition-all" style="animation: promoSlideUp 0.4s ease-out;">
                <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-yellow-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            <span class="text-white text-xs font-black">${views}ページも閲覧中！</span>
                        </div>
                        <button onclick="RegistrationPromo.dismissPopup()" class="text-white/60 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    
                    <div class="p-5">
                        <h4 class="text-base font-black text-gray-900 mb-1">無料登録でもっと便利に</h4>
                        <p class="text-xs text-gray-500 font-bold mb-4 leading-relaxed">お気に入り保存・値下げ通知・閲覧履歴がずっと使えます</p>
                        
                        <div class="space-y-2">
                            <a href="/auth/line/redirect" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl font-bold text-white text-sm shadow-sm transition-colors active:scale-95" style="background-color: #06C755;">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                LINEで10秒かんたん登録
                            </a>
                            <div class="flex gap-2">
                                <a href="/auth/google/redirect" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg font-bold text-gray-600 text-xs border border-gray-200 hover:bg-gray-50 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                                    Google
                                </a>
                                <a href="/register" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg font-bold text-gray-600 text-xs border border-gray-200 hover:bg-gray-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                    メール
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(popup);
    }

    function dismissPopup() {
        const popup = document.getElementById('promo-pageview-popup');
        if (popup) popup.remove();
        localStorage.setItem(POPUP_DISMISSED_KEY, Date.now().toString());
    }

    // ========================================
    // 公開API
    // ========================================
    return {
        init,
        showSearchBanner,
        dismissPopup,
        showFavoriteModal: _showFavoriteModal,
    };
})();

// グローバルに公開
window.RegistrationPromo = RegistrationPromo;