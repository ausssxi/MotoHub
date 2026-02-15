/**
 * MotoHub Browsing History Logic
 * 閲覧履歴の保存(LocalStorage)と、ウィジェットの描画を担当します。
 */
const HISTORY_KEY = 'motohub_history';
const MAX_HISTORY = 20;

const HistoryManager = {
    getIds() {
        try {
            const stored = localStorage.getItem(HISTORY_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            console.error('History load error', e);
            return [];
        }
    },

    push(id) {
        if (!id) return;
        let ids = this.getIds();
        ids = ids.filter(i => i !== id);
        ids.unshift(id);
        if (ids.length > MAX_HISTORY) ids = ids.slice(0, MAX_HISTORY);
        localStorage.setItem(HISTORY_KEY, JSON.stringify(ids));
    },

    async render(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const ids = this.getIds();
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
            bikes.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));

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
                // 画像表示ロジック (お気に入り一覧などと統一)
                let displayImage = PLACEHOLDER_IMG;
                let imgClass = 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500';
                let noImageOverlay = '';

                if (bike.images && bike.images.length > 0) {
                    displayImage = bike.images[0];
                } else {
                    imgClass += ' grayscale opacity-50';
                    // ★追加: 画像なしアイコン
                    noImageOverlay = `
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                        </div>
                    `;
                }
                
                // 価格表示ロジック
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