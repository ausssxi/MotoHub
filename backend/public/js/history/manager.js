/**
 * MotoHub Browsing History Logic
 * 閲覧履歴の保存(LocalStorage)と、ウィジェットの描画を担当します。
 */
const HISTORY_KEY = 'motohub_history';
const MAX_HISTORY = 20; // 最大保存件数

const HistoryManager = {
    // 保存されているIDを取得
    getIds() {
        try {
            const stored = localStorage.getItem(HISTORY_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            console.error('History load error', e);
            return [];
        }
    },

    // 履歴に追加（詳細ページ閲覧時に呼び出し）
    push(id) {
        if (!id) return;
        let ids = this.getIds();
        
        // 重複があれば削除し、先頭に追加し直す（「最近見た」順にするため）
        ids = ids.filter(i => i !== id);
        ids.unshift(id);
        
        // 上限を超えたら古いものを削除
        if (ids.length > MAX_HISTORY) {
            ids = ids.slice(0, MAX_HISTORY);
        }
        
        localStorage.setItem(HISTORY_KEY, JSON.stringify(ids));
    },

    // 指定したコンテナに「最近見た車両」ウィジェットを描画
    async render(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const ids = this.getIds();
        if (ids.length === 0) {
            container.classList.add('hidden');
            return;
        }

        try {
            // 既存のAPI（お気に入り取得用）を流用してデータ取得
            const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
            if (!response.ok) throw new Error('API Error');
            
            const json = await response.json();
            let bikes = json.data || json;

            if (!Array.isArray(bikes) || bikes.length === 0) {
                container.classList.add('hidden');
                return;
            }

            // IDの順番通り（最近見た順）に並び替え
            bikes.sort((a, b) => ids.indexOf(a.id) - ids.indexOf(b.id));

            // HTMLの生成（横スクロール・スナップ対応）
            let html = `
                <div class="px-4 sm:px-0 mb-4 flex items-center gap-2">
                    <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
                    <h3 class="text-lg font-black text-gray-800">最近見た車両</h3>
                </div>
                <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scroll-pl-4 sm:scroll-pl-0 -mx-4 px-4 sm:mx-0 sm:px-0 scrollbar-hide">
            `;

            bikes.forEach(bike => {
                const image = (bike.images && bike.images.length > 0) ? bike.images[0] : '/images/placeholder-bike.png';
                const price = bike.total_price 
                    ? `<span class="text-red-500 text-lg font-black">${bike.total_price}</span><span class="text-xs font-bold text-gray-500 ml-0.5">万円</span>`
                    : '<span class="text-gray-400 text-sm font-bold">価格未定</span>';

                // ★修正箇所: リンク先を内部の詳細ページに変更
                html += `
                    <a href="/bikes/${bike.id}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block">
                        <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                            <img src="${image}" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500" alt="">
                            ${bike.sold ? '<div class="absolute inset-0 bg-gray-900/50 flex items-center justify-center text-white font-black text-xs">SOLD OUT</div>' : ''}
                        </div>
                        <div class="p-3">
                            <div class="text-xs font-bold text-gray-400 mb-0.5 line-clamp-1">${bike.maker || ''}</div>
                            <h4 class="text-sm font-black text-gray-800 leading-tight line-clamp-2 mb-2 h-[2.5em]">${bike.name}</h4>
                            <div class="flex items-end justify-between">
                                <div>${price}</div>
                            </div>
                        </div>
                    </a>
                `;
            });

            html += `</div>`;
            
            container.innerHTML = html;
            container.classList.remove('hidden');

            // アイコン再描画
            if (window.lucide) window.lucide.createIcons();

        } catch (error) {
            console.error('History render error:', error);
            container.classList.add('hidden');
        }
    }
};

// グローバルに公開
window.HistoryManager = HistoryManager;