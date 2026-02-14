/**
 * MotoHub Wishlist Page Logic
 */

const initWishlistPage = async () => {
    const grid = document.getElementById('wishlist-grid');
    const empty = document.getElementById('wishlist-empty');
    const loading = document.getElementById('wishlist-loading');
    const totalLabel = document.getElementById('wishlist-total-label');

    if (!grid) return;

    // Managerの定義待ち
    let retryCount = 0;
    while (typeof WishlistManager === 'undefined' && retryCount < 20) {
        await new Promise(resolve => setTimeout(resolve, 100));
        retryCount++;
    }

    if (typeof WishlistManager === 'undefined') {
        console.error('WishlistManager not found');
        return;
    }

    // ★修正: IDの取得待ち (Managerの初期化完了を待つ)
    // Managerが存在しても、データロード(init)が終わっていない可能性があるため、
    // IDが取れるまで少しリトライします。
    let ids = [];
    let loadRetry = 0;
    while (loadRetry < 10) {
        ids = WishlistManager.getIds();
        if (ids.length > 0) break; // IDが取れたらループを抜ける
        
        // まだ0件なら少し待ってみる
        await new Promise(resolve => setTimeout(resolve, 200));
        loadRetry++;
    }
    
    console.log('Wishlist IDs loaded:', ids); // ★デバッグ用

    // それでもIDがなければ空表示
    if (ids.length === 0) {
        showEmptyState();
        return;
    }

    if (totalLabel) totalLabel.textContent = ids.length;

    try {
        const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
        if (!response.ok) throw new Error('API request failed');
        
        const data = await response.json(); // 実在する車両データのリスト
        console.log('Fetched data:', data); // ★デバッグ用
        
        if (loading) loading.classList.add('hidden');

        // データの自動クリーニング処理
        if (data) {
            const validIds = data.map(item => item.id);
            // ゴミデータ（DBにはあるが車両がないID）を抽出
            const staleIds = ids.filter(id => !validIds.includes(parseInt(id)));

            if (staleIds.length > 0) {
                console.log('Cleaning up stale favorites:', staleIds);
                // ゴミデータを1つずつ削除APIで消す
                staleIds.forEach(id => {
                    WishlistManager.toggle(id); 
                });
            }
            
            // Managerの内部リストも正しいものに同期
            WishlistManager.sync(validIds);
        }

        if (!data || data.length === 0) {
            showEmptyState();
            return;
        }

        renderWishlistItems(data, grid);
        
        if (totalLabel) totalLabel.textContent = data.length;
        if (window.lucide) window.lucide.createIcons();

    } catch (error) {
        console.error('Failed to load wishlist items:', error);
        if (loading) loading.innerHTML = '<p class="text-red-500 font-bold">データの読み込みに失敗しました。</p>';
    }

    function showEmptyState() {
        if (loading) loading.classList.add('hidden');
        if (grid) grid.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
        if (totalLabel) totalLabel.textContent = '0';
    }
};

// ... (renderWishlistItems 以降は変更なし) ...
/**
 * 車両カードの生成
 */
function renderWishlistItems(items, container) {
    container.innerHTML = items.map(bike => {
        // 画像配列の1枚目を取得、なければプレースホルダー
        const displayImage = (bike.images && bike.images.length > 0) 
            ? bike.images[0] 
            : 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';

        // お買い得バッジ
        let badgeHtml = '';
        if (bike.bargain_score > 5 && !bike.is_sold_out) {
            badgeHtml = `
                <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[10px] font-black px-2 py-1.5 rounded-tr-xl shadow-lg z-10 flex items-center gap-1">
                    <i data-lucide="trending-down" class="w-3.5 h-3.5"></i>
                    相場より約${Math.round(bike.bargain_score)}%お得！
                </div>`;
        }

        // 売り切れ表示
        let soldOutOverlay = '';
        if (bike.is_sold_out) {
            soldOutOverlay = `
                <div class="absolute inset-0 bg-gray-900/60 z-20 flex items-center justify-center">
                    <span class="text-white font-black text-xl tracking-widest border-2 border-white px-4 py-1 -rotate-12">SOLD OUT</span>
                </div>
            `;
        }

        return `
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative bike-card">
            <a href="/bikes/${bike.id}" class="absolute inset-0 z-10"></a>
            
            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                ${soldOutOverlay}
                
                <img src="${displayImage}" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ${bike.is_sold_out ? 'grayscale' : ''}" 
                     alt="${bike.name}"
                     onerror="this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.style.filter='grayscale(100%)'; this.style.opacity='0.5';">
                
                ${badgeHtml}

                <button class="wishlist-btn active absolute top-3 right-3 z-30 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-red-500 shadow-sm hover:scale-110 active:scale-90 transition-all border border-white/50" 
                        data-id="${bike.id}">
                    <i data-lucide="heart" class="w-5 h-5 fill-current"></i>
                </button>

                <div class="absolute bottom-3 right-3 z-10 bg-black/50 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/10 shadow-sm">
                    <span class="text-[8px] font-black text-white/90">${bike.source || 'MotoHub'}</span>
                </div>
            </div>

            <div class="p-5 flex-grow flex flex-col">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">${bike.maker}</span>
                    <span class="text-[9px] font-black text-purple-600 bg-purple-50 px-2 py-0.5 rounded uppercase">${bike.prefecture}</span>
                </div>
                <h3 class="text-sm font-black text-gray-800 mb-4 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">
                    ${bike.name}
                </h3>
                
                <div class="grid grid-cols-2 gap-y-2.5 gap-x-2 text-[10px] font-bold text-gray-400 mb-6">
                    <div class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i><span>${bike.model_year}</span></div>
                    <div class="flex items-center gap-1.5"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i><span>${bike.mileage}</span></div>
                </div>
                
                <div class="mt-auto bg-gray-50 p-4 rounded-xl border border-gray-100 group-hover:bg-blue-50/50 transition-all">
                    <div class="flex justify-between items-end mb-3">
                        <div>
                            <span class="text-[8px] font-black text-gray-400 block uppercase tracking-tighter mb-0.5">支払総額</span>
                            <div class="text-red-500 font-black italic">
                                <span class="text-2xl tracking-tighter">${bike.total_price}</span><span class="text-[10px] ml-0.5">万円</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-[8px] font-black text-gray-400 block uppercase">本体価格</span>
                            <div class="text-gray-700 font-black italic">
                                <span class="text-lg tracking-tighter">${bike.base_price}</span><span class="text-[9px] ml-0.5">万円</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-gray-200/50 flex items-center gap-1.5 text-[9px] text-gray-400 font-bold group-hover:text-blue-400 transition-colors">
                        <i data-lucide="store" class="w-3 h-3"></i><span class="truncate">${bike.store_name || '店舗情報なし'}</span>
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
        e.preventDefault(); 
        
        const card = btn.closest('.bike-card');
        const id = btn.dataset.id;
        
        if (card && id) {
            card.style.transition = 'all 0.3s ease';
            card.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                card.remove();
                if (window.WishlistManager) {
                    window.WishlistManager.toggle(id);
                }
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