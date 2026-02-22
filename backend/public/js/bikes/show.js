/**
 * MotoHub Vehicle Detail Page Entry Point
 * ページ固有の初期化処理（閲覧履歴の記録など）のみを担当します。
 * レビュー機能やシームレスナビは専用のJSファイルに委譲されています。
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // 閲覧履歴の記録とウィジェット描画のキック
    if (window.HistoryManager && typeof window.currentListingId !== 'undefined') {
        
        const authMeta = document.querySelector('meta[name="auth-check"]');
        const bodyLoggedIn = document.body.dataset.loggedIn === 'true';
        const isLoggedIn = (authMeta && authMeta.content === 'true') || bodyLoggedIn;
        
        const listingId = parseInt(window.currentListingId);
        
        if (!isNaN(listingId)) {
            // Managerの初期化
            HistoryManager.init(isLoggedIn).then(() => {
                // 現在の車両を履歴に追加
                HistoryManager.push(listingId).then(() => {
                    // 履歴ウィジェットの描画（現在の車両は除外して描画する）
                    HistoryManager.render('history-widget', listingId).then(() => {
                        const widget = document.getElementById('history-widget');
                        // 描画されたカードがあれば、セクション全体を表示する
                        if (widget && widget.children.length > 0) {
                            const section = document.getElementById('history-section');
                            if (section) section.classList.remove('hidden');
                        }
                    });
                });
            });
        }
    }
});