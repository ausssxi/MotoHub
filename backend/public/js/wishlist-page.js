/**
 * MotoHub Wishlist Page Logic
 * お気に入り一覧のデータ取得と表示管理を担当
 */

const initWishlistPage = async () => {
    const grid = document.getElementById('wishlist-grid');
    const empty = document.getElementById('wishlist-empty');
    const loading = document.getElementById('wishlist-loading');
    const totalLabel = document.getElementById('wishlist-total-label');

    if (!grid) return; // ページ内にグリッドがない場合は終了

    // 1. 共通ロジック（Wishlistオブジェクト）のロード待機
    // 読み込み順序によるエラーを防ぐため、最大2秒間チェックします
    let retryCount = 0;
    while (typeof Wishlist === 'undefined' && retryCount < 20) {
        await new Promise(resolve => setTimeout(resolve, 100));
        retryCount++;
    }

    if (typeof Wishlist === 'undefined') {
        console.error('Wishlist core logic not found.');
        return;
    }

    // 2. 保存されているIDを取得
    const ids = Wishlist.getIds();

    // 3. お気に入りが空の場合
    if (ids.length === 0) {
        if (loading) loading.classList.add('hidden');
        if (grid) grid.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
        return;
    }

    // カウントを先に表示
    if (totalLabel) totalLabel.textContent = ids.length;

    try {
        // 4. API経由で車両情報を取得
        const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
        if (!response.ok) throw new Error('API request failed');
        
        const data = await response.json();
        if (loading) loading.classList.add('hidden');

        if (!data || data.length === 0) {
            if (empty) empty.classList.remove('hidden');
            return;
        }

        // 5. HTMLを描画
        renderWishlistItems(data, grid);

        // 描画後にLucideアイコンを有効化
        if (window.lucide) window.lucide.createIcons();

    } catch (error) {
        console.error('Failed to load wishlist items:', error);
        if (loading) {
            loading.innerHTML = `
                <div class="text-red-400 text-center">
                    <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2"></i>
                    <p class="text-xs font-bold uppercase tracking-widest">データの読み込みに失敗しました</p>
                </div>
            `;
        }
        if (window.lucide) window.lucide.createIcons();
    }
};

/**
 * 車両カードの生成
 */
function renderWishlistItems(items, container) {
    container.innerHTML = items.map(bike => `
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative bike-card">
            <a href="${bike.url}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 z-10"></a>
            
            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                <img src="${bike.image || '/images/placeholder-bike.png'}" 
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
                                <span class="text-2xl tracking-tighter">${bike.price}</span><span class="text-[10px] ml-0.5">万円</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] text-gray-400 font-bold truncate max-w-[100px]">
                                ${bike.store || '店舗情報なし'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * ページ内での削除（ハート解除）イベント
 */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.wishlist-btn');
    const isWishlistPage = !!document.getElementById('wishlist-grid');

    if (btn && isWishlistPage) {
        const card = btn.closest('.bike-card');
        if (card) {
            // ふわっと消えるアニメーション
            card.classList.add('scale-95', 'opacity-0');
            card.style.pointerEvents = 'none';

            setTimeout(() => {
                card.remove();
                
                // 台数カウントの更新
                const remaining = document.querySelectorAll('.bike-card').length;
                const totalLabel = document.getElementById('wishlist-total-label');
                if (totalLabel) totalLabel.textContent = remaining;

                // 0台になったら空の状態を表示
                if (remaining === 0) {
                    document.getElementById('wishlist-grid').classList.add('hidden');
                    document.getElementById('wishlist-empty').classList.remove('hidden');
                }
            }, 300);
        }
    }
});

// 実行
document.addEventListener('DOMContentLoaded', initWishlistPage);