/**
 * MotoHub Comparison Page Logic
 * APIからデータを取得し、横並びの比較テーブルを生成します。
 */
document.addEventListener('DOMContentLoaded', async () => {
    // ... (冒頭の定義は同じなので省略) ...
    const header = document.getElementById('compare-header');
    const body = document.getElementById('compare-body');
    const container = document.getElementById('compare-container');
    const emptyState = document.getElementById('compare-empty');

    if (typeof Compare === 'undefined') {
        console.error("Compare manager is not loaded.");
        return;
    }

    const getNestedValue = (obj, path) => {
        return path.split('.').reduce((acc, part) => acc && acc[part], obj);
    };

    const ids = Compare.getIds();

    if (ids.length === 0) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (container) container.classList.add('hidden');
        return;
    }

    if (container) container.classList.remove('hidden');

    try {
        const response = await fetch(`/api/wishlist/fetch?ids=${ids.join(',')}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const json = await response.json();
        const bikes = json.data || json;

        if (!Array.isArray(bikes) || bikes.length === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        }

        // 3. ヘッダー（画像と名前）の描画
        bikes.forEach(bike => {
            const th = document.createElement('th');
            
            // 【スマホ対策】
            // 幅: スマホは w-48 (192px), PCは w-60 (240px)
            // 余白: スマホは p-2, PCは p-4
            // snap-start: スクロール時にここにピタッと止まる
            th.className = 'p-2 sm:p-4 w-48 sm:w-60 min-w-[192px] sm:min-w-[240px] max-w-[192px] sm:max-w-[240px] border-l border-gray-100 relative group snap-start bike-col-' + bike.id;
            
            const displayImage = (bike.images && bike.images.length > 0) 
                ? bike.images[0] 
                : '/images/placeholder-bike.png';

            th.innerHTML = `
                <button class="remove-this absolute top-1 right-1 sm:top-2 sm:right-2 text-gray-300 hover:text-red-500 transition-colors z-10" data-id="${bike.id}">
                    <i data-lucide="x-circle" class="w-5 h-5 sm:w-6 sm:h-6 bg-white/50 rounded-full"></i>
                </button>
                
                <div class="aspect-[4/3] rounded-lg sm:rounded-xl overflow-hidden mb-2 sm:mb-3 shadow-sm bg-gray-50 relative">
                    <img src="${displayImage}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                
                <div class="bike-name-container text-left font-bold text-gray-800 text-xs sm:text-sm leading-tight line-clamp-2 min-h-[2.5em]"></div>
                
                <a href="${bike.url}" target="_blank" class="mt-2 inline-flex items-center gap-1 text-[10px] font-black text-blue-600 hover:text-blue-700">
                    詳細 <i data-lucide="external-link" class="w-3 h-3"></i>
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
                    // スマホでは "万円" を改行させない、文字サイズ調整
                    td.innerHTML = `<span class="text-base sm:text-xl text-red-500">${val}</span><span class="text-[10px] ml-0.5">万円</span>`;
                
                } else if (prop === 'site_name') {
                    if (val !== '―') {
                        td.innerHTML = `<span class="inline-block bg-gray-100 text-gray-600 px-1.5 py-0.5 sm:px-2 sm:py-1 rounded text-[10px] font-black tracking-wider border border-gray-200 whitespace-nowrap">${val}</span>`;
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

        // 5. 削除ボタン (変更なし)
        document.querySelectorAll('.remove-this').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                Compare.toggle(id);
                const targets = document.querySelectorAll(`.bike-col-${id}`);
                targets.forEach(el => el.remove());

                if (Compare.getIds().length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                    if (container) container.classList.add('hidden');
                }
            });
        });

        if (window.lucide) window.lucide.createIcons();

    } catch (error) {
        console.error("Comparison Page Error:", error);
    }
});