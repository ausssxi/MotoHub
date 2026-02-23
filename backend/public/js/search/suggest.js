/**
 * MotoHub Search Suggest Logic (Universal & Rich Version)
 * トップページ、ナビゲーション(PC)、ナビゲーション(スマホ)の全検索窓をサポートし、
 * 車種の候補だけでなく「実際のバイク画像」もリッチに提案します。
 */
const initMotoHubSuggest = () => {
    /**
     * 各検索窓を初期化する内部関数
     */
    const setupSuggest = (config) => {
        const input = document.getElementById(config.inputId);
        const results = document.getElementById(config.resultsId);
        const list = document.getElementById(config.listId);
        const form = document.getElementById(config.formId);
        const container = document.getElementById(config.containerId);
        
        // 必須要素が不足している場合はスキップ
        if (!input || !results || !list || !form || !container) return;

        // 二重初期化を防止
        if (input.dataset.suggestInitialized) return;
        input.dataset.suggestInitialized = "true";

        let debounceTimer;

        // 入力監視
        input.addEventListener('input', (e) => {
            const keyword = e.target.value.trim();
            clearTimeout(debounceTimer);

            if (keyword.length < 1) {
                results.classList.add('hidden');
                return;
            }

            // 入力が止まってから300ms後にAPIを叩く
            debounceTimer = setTimeout(async () => {
                try {
                    // ★ 変更: ルーティングに合わせてエンドポイントを指定
                    const response = await fetch(`/bikes/suggest?keyword=${encodeURIComponent(keyword)}`);
                    if (!response.ok) throw new Error('API request failed');
                    
                    const data = await response.json();

                    // データが空なら非表示にする
                    if ((!data.models || data.models.length === 0) && (!data.listings || data.listings.length === 0)) {
                        results.classList.add('hidden');
                        return;
                    }

                    let html = '';

                    // --- 1. 関連する車種のサジェスト ---
                    if (data.models && data.models.length > 0) {
                        html += config.modelsHeaderTemplate();
                        data.models.forEach(item => {
                            html += config.modelTemplate(item);
                        });
                    }

                    // --- 2. 実際の車両のサジェスト（画像付き） ---
                    if (data.listings && data.listings.length > 0) {
                        html += config.listingsHeaderTemplate();
                        data.listings.forEach(bike => {
                            html += config.listingTemplate(bike);
                        });
                    }

                    list.innerHTML = html;
                    results.classList.remove('hidden');
                    
                    // Lucideアイコンの再描画
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    // 車種候補のクリックイベントの登録
                    list.querySelectorAll('.suggest-clickable').forEach(btn => {
                        btn.onclick = (event) => {
                            event.preventDefault();
                            input.value = btn.dataset.name;
                            results.classList.add('hidden');
                            form.submit();
                        };
                    });
                } catch (error) {
                    console.error('MotoHub Suggest Error:', error);
                    results.classList.add('hidden');
                }
            }, 300);
        });

        // 検索窓の外をクリックしたら閉じる
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target) && !results.contains(e.target)) {
                results.classList.add('hidden');
            }
        });

        // ESCキーで閉じる
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                results.classList.add('hidden');
                input.blur();
            }
        });
        
        // フォーカス時に再表示（文字が入っていれば）
        input.addEventListener('focus', () => {
            if (input.value.trim().length > 0 && list.innerHTML.trim() !== '') {
                results.classList.remove('hidden');
            }
        });
    };

    // ---------------------------------------------------------
    // 共通の画像付き車両テンプレート（すべての検索窓で使い回し）
    // ---------------------------------------------------------
    const commonListingTemplate = (bike) => {
        const imgSrc = bike.image ? bike.image : 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=200&auto=format&fit=crop';
        return `
            <a href="/bikes/${bike.id}" class="block p-3 hover:bg-blue-50 transition-colors flex items-start gap-3 border-b border-gray-50 last:border-0 group">
                <div class="w-16 h-12 sm:w-20 sm:h-14 rounded-lg overflow-hidden shrink-0 bg-gray-100 border border-gray-200 relative">
                    <img src="${imgSrc}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" onerror="this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=200&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] sm:text-xs font-black text-gray-800 truncate leading-tight mb-1 group-hover:text-blue-600">${bike.name}</div>
                    <div class="flex items-center gap-1.5 text-[9px] sm:text-[10px] font-bold text-gray-500">
                        <span class="text-red-500 font-black">${bike.total_price}万円</span>
                        <span class="text-gray-300">|</span>
                        <span>${bike.model_year}</span>
                        <span class="text-gray-300">|</span>
                        <span>${bike.mileage}</span>
                    </div>
                    <div class="text-[8px] sm:text-[9px] text-gray-400 mt-0.5 truncate w-full">
                        <i data-lucide="store" class="w-3 h-3 inline pb-0.5"></i> ${bike.shop_name || '店舗情報なし'}
                    </div>
                </div>
            </a>
        `;
    };

    // ---------------------------------------------------------
    // 各検索窓の初期化
    // ---------------------------------------------------------

    // 1. トップページ用 (大きな検索窓)
    try {
        setupSuggest({
            inputId: 'search-input',
            resultsId: 'suggest-results',
            listId: 'suggest-list',
            formId: 'search-form',
            containerId: 'search-container',
            modelsHeaderTemplate: () => `<div class="px-4 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-b border-gray-100 flex items-center gap-1.5"><i data-lucide="folder-search" class="w-3.5 h-3.5"></i> 関連する車種</div>`,
            modelTemplate: (item) => `
                <button type="button" class="w-full px-5 py-3 hover:bg-gray-50 flex items-center justify-between group transition-colors suggest-clickable" data-name="${item.name}">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 bg-gray-100 rounded-lg group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black">${item.name}</span>
                    </div>
                    <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-0.5 rounded">${item.count.toLocaleString()}台</span>
                </button>
            `,
            listingsHeaderTemplate: () => `<div class="px-4 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-t border-b border-gray-100 mt-1 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-yellow-500"></i> おすすめの車両</div>`,
            listingTemplate: commonListingTemplate
        });
    } catch (e) { console.error("Top search init failed", e); }

    // 2. ナビゲーション用 (ヘッダーのデスクトップ検索窓)
    try {
        setupSuggest({
            inputId: 'nav-search-input',
            resultsId: 'nav-suggest-results',
            listId: 'nav-suggest-list',
            formId: 'nav-search-form',
            containerId: 'nav-search-container',
            modelsHeaderTemplate: () => `<div class="px-3 py-1.5 text-[9px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-b border-gray-100 flex items-center gap-1"><i data-lucide="folder-search" class="w-3 h-3"></i> 関連する車種</div>`,
            modelTemplate: (item) => `
                <button type="button" class="w-full px-4 py-2.5 hover:bg-gray-50 flex items-center justify-between group transition-colors suggest-clickable" data-name="${item.name}">
                    <div class="flex items-center gap-2.5">
                        <div class="p-1 bg-gray-100 rounded group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                            <i data-lucide="search" class="w-3 h-3"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-700 group-hover:text-black">${item.name}</span>
                    </div>
                    <span class="text-[9px] font-black text-blue-500 bg-blue-50 px-1.5 py-0.5 rounded">${item.count.toLocaleString()}台</span>
                </button>
            `,
            listingsHeaderTemplate: () => `<div class="px-3 py-1.5 text-[9px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-t border-b border-gray-100 mt-1 flex items-center gap-1"><i data-lucide="sparkles" class="w-3 h-3 text-yellow-500"></i> おすすめの車両</div>`,
            listingTemplate: commonListingTemplate
        });
    } catch (e) { console.error("Nav search init failed", e); }

    // 3. ナビゲーション用 (モバイルドロップダウン検索窓)
    try {
        setupSuggest({
            inputId: 'mobile-nav-search-input',
            resultsId: 'mobile-nav-suggest-results',
            listId: 'mobile-nav-suggest-list',
            formId: 'mobile-nav-search-form',
            containerId: 'mobile-nav-search-bar', // トグルで表示されるコンテナ全体
            modelsHeaderTemplate: () => `<div class="px-4 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-b border-gray-100 flex items-center gap-1.5"><i data-lucide="folder-search" class="w-3.5 h-3.5"></i> 関連する車種</div>`,
            modelTemplate: (item) => `
                <button type="button" class="w-full px-4 py-3 hover:bg-gray-50 flex items-center justify-between group transition-colors suggest-clickable" data-name="${item.name}">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 bg-gray-100 rounded-lg group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black">${item.name}</span>
                    </div>
                    <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-0.5 rounded">${item.count.toLocaleString()}台</span>
                </button>
            `,
            listingsHeaderTemplate: () => `<div class="px-4 py-2 text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-50 border-t border-b border-gray-100 mt-1 flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-yellow-500"></i> おすすめの車両</div>`,
            listingTemplate: commonListingTemplate
        });
    } catch (e) { console.error("Mobile nav search init failed", e); }
};

// 実行タイミングの制御
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotoHubSuggest);
} else {
    initMotoHubSuggest();
}