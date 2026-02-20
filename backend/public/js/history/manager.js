/**
 * MotoHub Browsing History Logic
 * 閲覧履歴の保存(LocalStorage/DB)と、ウィジェットの描画を担当します。
 */
const HISTORY_KEY = 'motohub_history';
const MAX_HISTORY = 20;

const HistoryManager = {
    // 状態管理
    localIds: [],
    isLoggedIn: false,

    async init(loggedIn) {
        this.isLoggedIn = loggedIn;
        this.localIds = this.getLocalIds();

        if (this.isLoggedIn) {
            // 未ログイン時の履歴があればサーバーへ同期（統合）
            if (this.localIds.length > 0) {
                await this.syncToServer(this.localIds);
                localStorage.removeItem(HISTORY_KEY);
            }
        }
    },

    // ローカルストレージから取得
    getLocalIds() {
        try {
            const stored = localStorage.getItem(HISTORY_KEY);
            // 確実に数値の配列にして返す
            return stored ? JSON.parse(stored).map(id => parseInt(id)).filter(id => !isNaN(id)) : [];
        } catch (e) {
            console.error('History load error', e);
            return [];
        }
    },

    /**
     * 履歴に追加 (ページアクセス時に呼ぶ)
     */
    async push(id) {
        const listingId = parseInt(id);
        if (isNaN(listingId)) return;

        if (this.isLoggedIn) {
            // サーバーへ記録
            try {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const token = tokenMeta ? tokenMeta.content : '';
                
                await fetch('/api/history/record', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ listing_id: listingId })
                });
            } catch (e) {
                console.error('Failed to record history', e);
            }
        } else {
            // ローカルへ記録
            let ids = this.getLocalIds();
            // 重複排除（数値として厳密に比較）
            ids = ids.filter(i => i !== listingId); 
            ids.unshift(listingId); // 先頭に追加
            
            if (ids.length > MAX_HISTORY) ids = ids.slice(0, MAX_HISTORY);
            localStorage.setItem(HISTORY_KEY, JSON.stringify(ids));
        }
    },

    /**
     * サーバーへ同期
     */
    async syncToServer(ids) {
        try {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            if (!tokenMeta) return;

            await fetch('/api/history/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': tokenMeta.content
                },
                body: JSON.stringify({ ids: ids })
            });
        } catch (e) {
            console.error('Sync failed', e);
        }
    },

    /**
     * 履歴IDリストを取得
     */
    async fetchIds() {
        if (this.isLoggedIn) {
            try {
                const response = await fetch('/api/history/ids');
                if (response.ok) {
                    const data = await response.json();
                    return data.map(id => parseInt(id));
                }
            } catch (e) {
                console.error(e);
            }
            return [];
        } else {
            return this.getLocalIds();
        }
    },

    /**
     * 描画処理
     */
    async render(containerId, excludeId = null) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let ids = await this.fetchIds();

        // ★現在のページIDが履歴に含まれている場合は、表示から除外する
        if (excludeId !== null) {
            const exclude = parseInt(excludeId);
            ids = ids.filter(id => id !== exclude);
        }

        if (ids.length === 0) {
            container.classList.add('hidden');
            return;
        }

        try {
            // APIからデータ取得
            const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
            if (!response.ok) throw new Error('API Error');
            
            const json = await response.json();
            let bikes = json.data || json;

            if (!Array.isArray(bikes) || bikes.length === 0) {
                container.classList.add('hidden');
                return;
            }

            // 並び替え（履歴順）
            bikes.sort((a, b) => {
                const indexA = ids.indexOf(parseInt(a.id));
                const indexB = ids.indexOf(parseInt(b.id));
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });

            const PLACEHOLDER_IMG = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';
            let html = '';

            bikes.forEach(bike => {
                let displayImage = PLACEHOLDER_IMG;
                let imgClass = 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500';
                let noImageOverlay = '';

                if (bike.images && bike.images.length > 0) {
                    displayImage = bike.images[0];
                } else {
                    imgClass += ' grayscale opacity-50';
                    noImageOverlay = `
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                        </div>
                    `;
                }
                
                const priceBadge = bike.total_price 
                    ? `<div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">${bike.total_price}万円</div>`
                    : '<div class="absolute bottom-2 right-2 bg-gray-500/80 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-bold">価格未定</div>';

                html += `
                    <a href="/bikes/${bike.id}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block relative">
                        <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                            <img src="${displayImage}" 
                                 onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}'; this.classList.add('grayscale', 'opacity-50');"
                                 class="${imgClass}" 
                                 alt="${bike.name}">
                            
                            ${noImageOverlay}
                            ${priceBadge}
                        </div>
                        <div class="p-3">
                            <div class="text-[10px] font-bold text-gray-400 mb-0.5 flex items-center gap-1">
                                <span class="bg-gray-100 px-1.5 rounded">${bike.model_year || '不明'}</span>
                                <span>${bike.mileage || '不明'}</span>
                            </div>
                            <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 h-[2.5em] group-hover:text-blue-600 transition-colors">
                                ${bike.name}
                            </h4>
                            <div class="flex items-end justify-between border-t border-gray-100 pt-2">
                                <div class="text-[10px] text-gray-400 truncate w-full">${bike.prefecture || '全国'}</div>
                            </div>
                        </div>
                    </a>
                `;
            });

            container.innerHTML = html;
            container.classList.remove('hidden');

            if (window.lucide) window.lucide.createIcons();

        } catch (error) {
            console.error('History render error:', error);
            container.classList.add('hidden');
        }
    }
};

window.HistoryManager = HistoryManager;