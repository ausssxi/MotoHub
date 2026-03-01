/**
 * MotoHub Browsing History Logic (Ultra Fast Version)
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
            return stored ? JSON.parse(stored).map(id => parseInt(id)).filter(id => !isNaN(id)) : [];
        } catch (e) {
            return [];
        }
    },

    /**
     * 履歴に追加
     */
    async push(id) {
        const listingId = parseInt(id);
        if (isNaN(listingId)) return;

        if (this.isLoggedIn) {
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
            let ids = this.getLocalIds();
            ids = ids.filter(i => i !== listingId); 
            ids.unshift(listingId); 
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
        } catch (e) {}
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
            } catch (e) {}
            return [];
        } else {
            return this.getLocalIds();
        }
    },

    /**
     * 描画処理（★高速化: 重いlucide.createIcons()を使わずに生SVGを描画）
     */
    async render(containerId, excludeId = null) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let ids = await this.fetchIds();

        if (excludeId !== null) {
            ids = ids.filter(id => id !== parseInt(excludeId));
        }

        if (ids.length === 0) {
            container.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
            if (!response.ok) throw new Error('API Error');
            
            const json = await response.json();
            let bikes = json.data || json;

            if (!Array.isArray(bikes) || bikes.length === 0) {
                container.classList.add('hidden');
                return;
            }

            bikes.sort((a, b) => {
                const indexA = ids.indexOf(parseInt(a.id));
                const indexB = ids.indexOf(parseInt(b.id));
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });

            const PLACEHOLDER_IMG = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';
            let html = '';

            // ★生SVGの定義（描画コスト最小化）
            const svgImageOff = `<svg class="w-8 h-8 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="2" x2="22" y2="22"></line><path d="M10.41 10.41a2 2 0 1 1-2.83-2.83"></path><line x1="13.5" y1="13.5" x2="6" y2="21"></line><line x1="18" y1="12" x2="21" y2="15"></line><path d="M3.59 3.59A1.99 1.99 0 0 0 3 5v14a2 2 0 0 0 2 2h14c.55 0 1.05-.22 1.41-.59"></path><path d="M21 15V5a2 2 0 0 0-2-2H9"></path></svg>`;
            const svgTrendingDown = `<svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="16 17 22 17 22 11"></polyline></svg>`;

            bikes.forEach(bike => {
                let displayImage = PLACEHOLDER_IMG;
                let imgClass = 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500';
                let noImageOverlay = '';

                if (bike.images && bike.images.length > 0) {
                    displayImage = bike.images[0];
                } else {
                    imgClass += ' grayscale opacity-50';
                    noImageOverlay = `<div class="absolute inset-0 flex items-center justify-center pointer-events-none">${svgImageOff}</div>`;
                }
                
                const priceBadge = bike.total_price 
                    ? `<div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">${bike.total_price}万円</div>`
                    : '<div class="absolute bottom-2 right-2 bg-gray-500/80 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-bold">価格未定</div>';

                let bargainBadge = '';
                if (bike.bargain_score && parseFloat(bike.bargain_score) > 5) {
                    bargainBadge = `
                        <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[9px] font-black px-1.5 py-1 rounded-tr-xl shadow-lg z-10 flex items-center gap-1">
                            ${svgTrendingDown}
                            約${Math.round(parseFloat(bike.bargain_score))}%お得！
                        </div>
                    `;
                }

                html += `
                    <a href="/bikes/${bike.id}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block relative">
                        <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                            <img src="${displayImage}" 
                                 onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}'; this.classList.add('grayscale', 'opacity-50');"
                                 class="${imgClass}" 
                                 alt="${bike.name}">
                            
                            ${noImageOverlay}
                            ${bargainBadge}
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

            // ★削除: ここにあった lucide.createIcons() を消したことで、ページがフリーズしなくなります！

        } catch (error) {
            console.error('History render error:', error);
            container.classList.add('hidden');
        }
    }
};

window.HistoryManager = HistoryManager;