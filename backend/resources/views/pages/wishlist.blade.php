<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        お気に入り一覧 - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    {{-- 検索窓を表示し、掲載台数(totalListingsCount)を渡します --}}
    <x-slot:navigation>
        <x-navigation 
            :totalListingsCount="$totalListingsCount ?? 0" 
            :showSearch="true" 
        />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-gray-50 min-h-[calc(100vh-64px)] py-12 sm:py-20">
        <div class="max-w-7xl mx-auto px-4">
            
            {{-- ページヘッダー --}}
            <div class="mb-12 flex flex-col sm:flex-row sm:items-end justify-between border-b border-gray-200 pb-8 gap-4">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-black text-black mb-2 tracking-tighter">
                        お気に入り一覧
                    </h1>
                    <p class="text-gray-400 text-xs font-bold tracking-widest uppercase flex items-center gap-2">
                        <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-current"></i>
                        あなたがチェックしたバイク
                    </p>
                </div>
                <div class="text-right">
                    <span id="wishlist-total-label" class="text-3xl font-black text-black tabular-nums">0</span>
                    <span class="text-[10px] font-bold text-gray-400 ml-1 uppercase">台</span>
                </div>
            </div>

            {{-- お気に入り一覧表示エリア --}}
            <div id="wishlist-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 min-h-[400px]">
                {{-- JSでここにカードを流し込みます --}}
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-300" id="wishlist-loading">
                    <i data-lucide="loader-2" class="w-10 h-10 animate-spin mb-4"></i>
                    <p class="text-xs font-bold uppercase tracking-widest">Loading your bikes...</p>
                </div>
            </div>

            {{-- 空の状態の表示（初期は非表示） --}}
            <div id="wishlist-empty" class="hidden py-32 text-center">
                <div class="mb-8 inline-flex items-center justify-center w-24 h-24 bg-white rounded-full text-gray-200 shadow-sm border border-gray-100">
                    <i data-lucide="heart-off" class="w-10 h-10"></i>
                </div>
                <h2 class="text-xl font-black text-gray-900 mb-2 tracking-tight">お気に入りはまだありません</h2>
                <p class="text-sm text-gray-400 mb-10 max-w-sm mx-auto leading-relaxed">
                    気になるバイクを見つけたら、カードのハートマークをタップして保存しましょう。
                </p>
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 bg-black text-white px-10 py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-gray-800 transition-all active:scale-95 shadow-xl shadow-black/10">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    バイクを探しに行く
                </a>
            </div>
        </div>
    </div>

    {{-- お気に入り専用ロジック --}}
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // wishlist.js が正しく読み込まれていることを前提とします
            if (typeof Wishlist === 'undefined') {
                console.error('Wishlist logic not found. Please ensure wishlist.js is loaded.');
                return;
            }

            const ids = Wishlist.getIds();
            const grid = document.getElementById('wishlist-grid');
            const empty = document.getElementById('wishlist-empty');
            const loading = document.getElementById('wishlist-loading');
            const totalLabel = document.getElementById('wishlist-total-label');

            if (ids.length === 0) {
                loading.classList.add('hidden');
                grid.classList.add('hidden');
                empty.classList.remove('hidden');
                return;
            }

            totalLabel.textContent = ids.length;

            try {
                // API経由で車両情報を取得
                const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
                if (!response.ok) throw new Error('API request failed');
                
                const data = await response.json();
                loading.classList.add('hidden');

                if (data.length === 0) {
                    empty.classList.remove('hidden');
                    return;
                }

                // 検索結果ページとデザインを統一したカードを描画
                grid.innerHTML = data.map(bike => `
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative bike-card">
                        <a href="${bike.url}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 z-10"></a>
                        
                        <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                            <img src="${bike.image || '/images/placeholder-bike.png'}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="">
                            
                            <!-- 削除ボタン（ハート解除） -->
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

                // Lucideアイコンを再描画
                if (window.lucide) window.lucide.createIcons();

            } catch (error) {
                console.error('Failed to load wishlist items:', error);
                loading.innerHTML = `
                    <div class="text-red-400 text-center">
                        <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2"></i>
                        <p class="text-xs font-bold uppercase tracking-widest">Failed to load data</p>
                    </div>
                `;
                if (window.lucide) window.lucide.createIcons();
            }
        });

        // リストから消した際、グリッドから即座に消去し、台数を更新する補助ロジック
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.wishlist-btn');
            if (btn && btn.classList.contains('active')) {
                // ウィッシュリストページでのみ、削除した瞬間にカードを消す
                const card = btn.closest('.bike-card');
                if (card) {
                    card.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        card.remove();
                        const remaining = document.querySelectorAll('.bike-card').length;
                        document.getElementById('wishlist-total-label').textContent = remaining;
                        if (remaining === 0) {
                            document.getElementById('wishlist-grid').classList.add('hidden');
                            document.getElementById('wishlist-empty').classList.remove('hidden');
                        }
                    }, 300);
                }
            }
        });
    </script>
</x-layout>