/**
 * MotoHub Comparison Page Logic
 * APIからデータを取得し、横並びの比較テーブルを生成します。
 */
document.addEventListener('DOMContentLoaded', async () => {
    // 必要なDOM要素の取得
    const header = document.getElementById('compare-header');
    const body = document.getElementById('compare-body');
    const container = document.getElementById('compare-container');
    const emptyState = document.getElementById('compare-empty');

    // CompareManagerの読み込み確認
    if (typeof CompareManager === 'undefined') {
        console.error("Compare manager is not loaded.");
        return;
    }

    // ネストされたオブジェクトから値を取得するヘルパー関数
    const getNestedValue = (obj, path) => {
        return path.split('.').reduce((acc, part) => acc && acc[part], obj);
    };

    // 保存されたIDを取得
    const ids = CompareManager.getIds();

    // IDがない場合は空の状態を表示
    if (ids.length === 0) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (container) container.classList.add('hidden');
        return;
    }

    // コンテナを表示
    if (container) container.classList.remove('hidden');

    try {
        // サーバーからデータを取得
        const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const json = await response.json();
        const bikes = json.data || json;

        if (!Array.isArray(bikes) || bikes.length === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
            if (container) container.classList.add('hidden');
            return;
        }

        // ダミー画像のURL定義
        const PLACEHOLDER_IMG = 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop';

        // 3. ヘッダー（画像と名前）の描画
        bikes.forEach(bike => {
            const th = document.createElement('th');
            
            // 【スマホ対策】幅と余白の設定
            th.className = 'p-2 sm:p-4 w-48 sm:w-60 min-w-[192px] sm:min-w-[240px] max-w-[192px] sm:max-w-[240px] border-l border-gray-100 relative group snap-start bike-col-' + bike.id;
            
            // 画像がある場合はそれを使用、なければプレースホルダーURLを使用
            // 画像がない場合は最初からグレーアウトさせるためのフラグ
            let displayImage;
            let initialClass = '';
            let noImageOverlay = '';

            if (bike.images && bike.images.length > 0) {
                displayImage = bike.images[0];
            } else {
                displayImage = PLACEHOLDER_IMG;
                // 画像がない場合は最初からグレーアウトと透明度を適用
                initialClass = 'grayscale opacity-50';
                // アイコン追加
                noImageOverlay = `
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <i data-lucide="image-off" class="w-8 h-8 text-white/50"></i>
                    </div>
                `;
            }

            // ★追加: お買い得バッジのHTML
            let bargainBadge = '';
            if (bike.bargain_score && parseFloat(bike.bargain_score) > 5 && !bike.is_sold_out) {
                bargainBadge = `
                    <div class="absolute bottom-0 left-0 bg-red-600 text-white text-[9px] sm:text-[10px] font-black px-1.5 sm:px-2 py-1 sm:py-1.5 rounded-tr-xl shadow-lg z-10 flex items-center gap-1">
                        <i data-lucide="trending-down" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                        約${Math.round(parseFloat(bike.bargain_score))}%お得！
                    </div>
                `;
            }

            // ★追加: サイト名バッジのHTML
            let siteBadge = `
                <div class="absolute bottom-2 right-2 z-10 bg-black/50 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/10 shadow-sm">
                    <span class="text-[8px] font-black text-white/90">${bike.source || 'MotoHub'}</span>
                </div>
            `;

            // 【修正】高さを固定値 (スマホ: h-36=144px, PC: sm:h-[180px]=180px) にしてテーブル内での画像潰れバグを完全に防ぎます。
            th.innerHTML = `
                <div class="rounded-lg sm:rounded-xl overflow-hidden mb-2 sm:mb-3 shadow-sm bg-gray-50 relative w-full h-36 sm:h-[180px] flex items-center justify-center">
                    
                    <!-- ★変更: 削除ボタン（右上）を画像内に移動し、お気に入りボタンとデザインを統一 -->
                    <button class="remove-this absolute top-2 right-2 z-30 w-8 h-8 sm:w-10 sm:h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm border border-gray-100 transition-all hover:scale-110 active:scale-95" data-id="${bike.id}">
                        <i data-lucide="x" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    </button>

                    <!-- ★追加: お気に入りボタン（左上） -->
                    <button class="wishlist-btn absolute top-2 left-2 z-20 w-8 h-8 sm:w-10 sm:h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm border border-gray-100 transition-all hover:scale-110 active:scale-95" data-id="${bike.id}">
                        <i data-lucide="heart" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    </button>

                    <!-- 画像本体（統一感を出すため object-cover に変更） -->
                    <img src="${displayImage}" 
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ${initialClass}"
                         alt="${bike.name}"
                         onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}'; this.classList.add('grayscale', 'opacity-50');">
                    
                    ${noImageOverlay}
                    ${bargainBadge}
                    ${siteBadge}
                </div>

                <div class="bike-name-container text-left font-bold text-gray-800 text-xs sm:text-sm leading-tight line-clamp-2 min-h-[2.5em] mb-1"></div>
                
                <a href="/bikes/${bike.id}" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-black text-blue-600 hover:text-blue-700 bg-blue-50 px-2 py-1 rounded-lg transition-colors mt-1">
                    詳細を見る <i data-lucide="external-link" class="w-3 h-3"></i>
                </a>
            `;
            
            th.querySelector('.bike-name-container').textContent = bike.name;
            header.appendChild(th);
        });

        // 4. スペック行の描画
        const rows = body.querySelectorAll('tr[data-prop]');
        rows.forEach(row => {
            const prop = row.dataset.prop;
            bikes.forEach(bike => {
                const td = document.createElement('td');
                // 【スマホ対策】幅と余白をヘッダーに合わせる
                td.className = `p-2 sm:p-4 w-48 sm:w-60 min-w-[192px] sm:min-w-[240px] max-w-[192px] sm:max-w-[240px] border-l border-gray-100 text-center font-bold text-gray-700 snap-start bike-col-${bike.id}`;
                
                let rawVal = getNestedValue(bike, prop);
                let val = rawVal ?? '―';
                
                if (prop === 'total_price') {
                    // 価格表示の装飾
                    td.innerHTML = `<span class="text-base sm:text-xl text-red-500 font-black tracking-tighter">${val}</span><span class="text-[10px] ml-0.5 font-bold text-gray-500">万円</span>`;
                
                } else if (prop === 'site_name') {
                    if (val !== '―') {
                        td.innerHTML = `<span class="inline-block bg-gray-100 text-gray-600 px-2 py-1 rounded text-[10px] font-black tracking-wider border border-gray-200 whitespace-nowrap">${val}</span>`;
                    } else {
                        td.textContent = val;
                    }

                } else {
                    if (prop === 'mileage' && typeof val === 'number') {
                        val = `${val.toLocaleString()}km`;
                    } else if (prop === 'model_year' && val !== '―') {
                        val = String(val).includes('年') ? val : `${val}年`;
                    }
                    td.textContent = val;
                }

                row.appendChild(td);
            });
        });

        // 5. 削除ボタンのイベントリスナー設定
        document.querySelectorAll('.remove-this').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                
                // CompareManagerを使って削除
                CompareManager.toggle(id);
                
                // 画面から列を削除 (アニメーション付きで消すとベターですが今回は即時削除)
                const targets = document.querySelectorAll(`.bike-col-${id}`);
                targets.forEach(el => el.remove());

                // 全てなくなった場合の表示切り替え
                if (CompareManager.getIds().length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                    if (container) container.classList.add('hidden');
                }
            });
        });

        // アイコンの再描画
        if (window.lucide) window.lucide.createIcons();
        
        // ★追加: 描画されたお気に入りボタンの初期状態（色）を反映
        if (typeof WishlistManager !== 'undefined') {
            WishlistManager.updateUI();
        }

    } catch (error) {
        console.error("Comparison Page Error:", error);
    }
});