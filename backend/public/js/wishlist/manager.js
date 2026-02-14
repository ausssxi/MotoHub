const WishlistManager = {
    // 状態管理
    items: new Set(),
    isLoggedIn: false, // ページ読み込み時にセットする

    /**
     * 初期化処理
     * @param {boolean} loggedIn ログイン状態
     */
    async init(loggedIn) {
        this.isLoggedIn = loggedIn;
        
        if (this.isLoggedIn) {
            // サーバーからお気に入りID一覧を取得
            try {
                const response = await fetch('/api/favorites/ids');
                if (response.ok) {
                    const ids = await response.json();
                    this.items = new Set(ids.map(id => parseInt(id)));
                    
                    // LocalStorageからの移行（必要なら）
                    this.syncLocalStorageToServer();
                }
            } catch (e) {
                console.error('Failed to fetch favorites', e);
            }
        } else {
            // LocalStorageから読み込み
            const stored = localStorage.getItem('motohub_wishlist');
            if (stored) {
                this.items = new Set(JSON.parse(stored).map(id => parseInt(id)));
            }
        }

        this.updateUI();
    },

    /**
     * トグル処理（追加・削除）
     */
    async toggle(id) {
        const listingId = parseInt(id);

        if (this.isLoggedIn) {
            // サーバーと通信
            try {
                // UIを先行更新 (Optimistic UI)
                if (this.items.has(listingId)) {
                    this.items.delete(listingId);
                } else {
                    this.items.add(listingId);
                }
                this.updateUI(); // 即時反映

                // APIリクエスト
                const response = await fetch('/api/favorites/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ listing_id: listingId })
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
            } catch (e) {
                console.error('Toggle failed', e);
                // エラー時は元に戻す（ロールバック）
                if (this.items.has(listingId)) {
                    this.items.delete(listingId);
                } else {
                    this.items.add(listingId);
                }
                this.updateUI();
                alert('お気に入りの更新に失敗しました。');
            }
        } else {
            // LocalStorage操作 (未ログイン時)
            if (this.items.has(listingId)) {
                this.items.delete(listingId);
            } else {
                this.items.add(listingId);
            }
            localStorage.setItem('motohub_wishlist', JSON.stringify([...this.items]));
            this.updateUI();
        }
    },

    /**
     * LocalStorageのデータをサーバーへ移行（ログイン直後など）
     */
    async syncLocalStorageToServer() {
        const stored = localStorage.getItem('motohub_wishlist');
        if (!stored) return;
        
        const localIds = JSON.parse(stored);
        if (localIds.length === 0) return;

        // サーバーへ一括送信するAPIがあれば良いが、今回は簡易的にループで送信
        for (const id of localIds) {
            if (!this.items.has(parseInt(id))) {
                await this.toggle(id);
            }
        }

        // 移行完了後はクリア
        localStorage.removeItem('motohub_wishlist');
    },

    /**
     * UIの更新（ハートアイコンの色など）
     */
    updateUI() {
        // すべてのお気に入りボタンの状態を更新
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            const id = parseInt(btn.dataset.id);
            const icon = btn.querySelector('i');
            
            if (this.items.has(id)) {
                btn.classList.add('active', 'text-red-500', 'bg-red-50', 'border-red-200');
                btn.classList.remove('text-gray-400', 'bg-white', 'border-gray-200');
                if(icon) icon.setAttribute('fill', 'currentColor');
            } else {
                btn.classList.remove('active', 'text-red-500', 'bg-red-50', 'border-red-200');
                btn.classList.add('text-gray-400', 'bg-white', 'border-gray-200');
                if(icon) icon.setAttribute('fill', 'none');
            }
        });

        // ヘッダーのカウントバッジ更新（もしあれば）
        const badge = document.getElementById('wishlist-count');
        if (badge) {
            badge.textContent = this.items.size;
            badge.style.display = this.items.size > 0 ? 'flex' : 'none';
        }
    },

    /**
     * 一覧取得（APIまたはローカル）
     */
    getIds() {
        return [...this.items];
    }, // ★カンマを追加

    /**
     * ★追加: 有効なIDリストで強制的に同期する
     */
    sync(validIds) {
        // 新しいIDリストで上書き
        this.items = new Set(validIds.map(id => parseInt(id)));

        // 未ログインならLocalStorageも更新して永続化
        if (!this.isLoggedIn) {
            localStorage.setItem('motohub_wishlist', JSON.stringify([...this.items]));
        }
        
        // UI（バッジの数字）を即座に更新
        this.updateUI();
    }
};

// グローバル公開
window.WishlistManager = WishlistManager;