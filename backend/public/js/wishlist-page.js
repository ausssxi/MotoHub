/**
 * MotoHub Wishlist Page Logic
 * API (ListingResource) の形式に合わせてキー名を修正しました。
 */

const initWishlistPage = async () => {
    const grid = document.getElementById('wishlist-grid');
    const empty = document.getElementById('wishlist-empty');
    const loading = document.getElementById('wishlist-loading');
    const totalLabel = document.getElementById('wishlist-total-label');

    if (!grid) return;

    let retryCount = 0;
    while (typeof Wishlist === 'undefined' && retryCount < 20) {
        await new Promise(resolve => setTimeout(resolve, 100));
        retryCount++;
    }

    if (typeof Wishlist === 'undefined') return;

    const ids = Wishlist.getIds();

    if (ids.length === 0) {
        if (loading) loading.classList.add('hidden');
        if (grid) grid.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
        return;
    }

    if (totalLabel) totalLabel.textContent = ids.length;

    try {
        const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
        if (!response.ok) throw new Error('API request failed');
        
        const data = await response.json();
        if (loading) loading.classList.add('hidden');

        if (!data || data.length === 0) {
            if (empty) empty.classList.remove('hidden');
            return;
        }

        // 描画実行
        renderWishlistItems(data, grid);

        if (window.lucide) window.lucide.createIcons();

    } catch (error) {
        console.error('Failed to load wishlist items:', error);
    }
};

/**
 * 車両カードの生成
 * ✨ 修正ポイント: bike.total_price, bike.images[0], bike.store_name を使用
 */
function renderWishlistItems(items, container) {
    container.innerHTML = items.map(bike => {
        // 画像配列の1枚目を取得、なければプレースホルダー
        const displayImage = (bike.images && bike.images.length > 0) 
            ? bike.images[0] 
            : '/images/placeholder-bike.png';

        return `
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative bike-card">
            <a href="${bike.url}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 z-10"></a>
            
            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                <img src="${displayImage}" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" 
                     alt="${bike.name}"
                     onerror="this.src='/images/placeholder-bike.png'">
                
                <button class="wishlist-btn active absolute top-3 right-3 z-20 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-red-500 shadow-sm hover:scale-110 active:scale-90 transition-all border border-white/50" 
                        data-id="${bike.id}">
                    <i data-lucide="heart" class="w-5 h-5 fill-current"></i>
                </button>
            </div>

            <div class="p-5 flex-grow flex flex-col">
                <h3 class="text-sm font-black text-gray-800 mb-4 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">
                    ${bike.name}
                </h3>
                
                <div class="mt-auto bg-gray-50 p-4 rounded-xl border border-gray-100 group-hover:bg-blue-50/50 transition-all">
                    <div class="flex justify-between items-end">
                        <div>
                            <span class="text-[8px] font-black text-gray-400 block uppercase tracking-tighter mb-0.5">支払総額</span>
                            <div class="text-red-500 font-black italic">
                                <span class="text-2xl tracking-tighter">${bike.total_price}</span><span class="text-[10px] ml-0.5">万円</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] text-gray-400 font-bold truncate max-w-[100px]">
                                ${bike.store_name || '店舗情報なし'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}

// 削除イベントの監視（変更なし）
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.wishlist-btn');
    if (btn && document.getElementById('wishlist-grid')) {
        const card = btn.closest('.bike-card');
        if (card) {
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                card.remove();
                const remaining = document.querySelectorAll('.bike-card').length;
                const totalLabel = document.getElementById('wishlist-total-label');
                if (totalLabel) totalLabel.textContent = remaining;
                if (remaining === 0) {
                    document.getElementById('wishlist-grid').classList.add('hidden');
                    document.getElementById('wishlist-empty').classList.remove('hidden');
                }
            }, 300);
        }
    }
});

document.addEventListener('DOMContentLoaded', initWishlistPage);