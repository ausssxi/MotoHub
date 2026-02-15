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
            // 1. 未ログイン時の履歴があればサーバーへ同期（統合）
            if (this.localIds.length > 0) {
                await this.syncToServer(this.localIds);
                // 同期後はローカルをクリアして、今後はサーバーから取得するようにする？
                // あるいはハイブリッドにする？ -> 今回は「常に最新IDリストをAPIから取る」形にする
                localStorage.removeItem(HISTORY_KEY);
            }
        }
    },

    // ローカルストレージから取得
    getLocalIds() {
        try {
            const stored = localStorage.getItem(HISTORY_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            console.error('History load error', e);
            return [];
        }
    },

    /**
     * 履歴に追加 (ページアクセス時に呼ぶ)
     */
    async push(id) {
        if (!id) return;

        if (this.isLoggedIn) {
            // サーバーへ記録
            try {
                // CSRFトークンが必要
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const token = tokenMeta ? tokenMeta.content : '';
                
                await fetch('/api/history/record', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ listing_id: id })
                });
            } catch (e) {
                console.error('Failed to record history', e);
            }
        } else {
            // ローカルへ記録
            let ids = this.getLocalIds();
            ids = ids.filter(i => i !== id); // 重複排除
            ids.unshift(id); // 先頭に追加
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
     * 履歴IDリストを取得（状況に応じてソースを変える）
     */
    async fetchIds() {
        if (this.isLoggedIn) {
            try {
                const response = await fetch('/api/history/ids');
                if (response.ok) {
                    return await response.json();
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
    async render(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // ★修正: 非同期でIDリストを取得
        const ids = await this.fetchIds();

        if (ids.length === 0) {
            container.classList.add('hidden');
            return;
        }

        try {
            // APIからデータ取得 (ListingResourceの形式)
            const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
            if (!response.ok) throw new Error('API Error');
            
            const json = await response.json();
            let bikes = json.data || json;

            if (!Array.isArray(bikes) || bikes.length === 0) {
                container.classList.add('hidden');
                return;
            }

            // 並び替え（履歴順＝IDリストの順序に合わせる）
            // ids配列の順番通りに bikes をソートする
            bikes.sort((a, b) => {
                const indexA = ids.indexOf(a.id);
                const indexB = ids.indexOf(b.id);
                // IDリストにないものは後ろへ
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });

            // ダミー画像URL
            const PLACEHOLDER_IMG = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';

            // HTML生成
            let html = `
                <div class="flex items-center gap-2 mb-6">
                    <div class="p-2 bg-gray-100 rounded-lg text-gray-600">
                        <i data-lucide="history" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-lg font-black text-gray-900">最近見た車両</h3>
                </div>
                <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            `;

            bikes.forEach(bike => {
                // 画像表示ロジック
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
                    <a href="/bikes/${bike.id}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block">
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

            html += `</div>`;
            
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