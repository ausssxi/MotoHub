/**
 * MotoHub Search Suggest Logic (Universal & Resilient Version)
 * トップページとナビゲーションの検索窓を独立して確実に初期化します。
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
        
        // 必須要素が一つでも足りない場合は、この検索窓の初期化だけをスキップ（他には影響させない）
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

            debounceTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`/bikes/suggest?keyword=${encodeURIComponent(keyword)}`);
                    if (!response.ok) throw new Error('API request failed');
                    
                    const data = await response.json();

                    if (data && data.length > 0) {
                        // リストの生成
                        list.innerHTML = data.map(item => config.template(item)).join('');
                        results.classList.remove('hidden');
                        
                        // Lucideアイコンの再描画
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }

                        // 候補クリックイベントの登録（より確実な addEventListener を使用）
                        list.querySelectorAll('.suggest-clickable').forEach(btn => {
                            btn.addEventListener('click', (event) => {
                                event.preventDefault();
                                input.value = btn.dataset.name;
                                results.classList.add('hidden');
                                form.submit();
                            });
                        });
                    } else {
                        results.classList.add('hidden');
                    }
                } catch (error) {
                    console.error('MotoHub Suggest Error:', error);
                    results.classList.add('hidden');
                }
            }, 300);
        });

        // 検索窓の外をクリックしたら閉じる
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
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
    };

    // ---------------------------------------------------------
    // 各検索窓の初期化（片方が失敗してももう片方に影響しないよう独立して実行）
    // ---------------------------------------------------------

    // 1. トップページ用 (大きな検索窓)
    try {
        setupSuggest({
            inputId: 'search-input',
            resultsId: 'suggest-results',
            listId: 'suggest-list',
            formId: 'search-form',
            containerId: 'search-container',
            template: (item) => `
                <button type="button" class="w-full px-5 py-3 hover:bg-gray-50 flex items-center justify-between group transition-colors suggest-clickable" data-name="${item.name}">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 bg-gray-100 rounded-lg group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                            <i data-lucide="bike" class="w-3.5 h-3.5"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700 group-hover:text-black">${item.name}</span>
                    </div>
                    <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-0.5 rounded">${item.count.toLocaleString()}台</span>
                </button>
            `
        });
    } catch (e) { console.error("Top search init failed", e); }

    // 2. ナビゲーション用 (ヘッダーの検索窓)
    try {
        setupSuggest({
            inputId: 'nav-search-input',
            resultsId: 'nav-suggest-results',
            listId: 'nav-suggest-list',
            formId: 'nav-search-form',
            containerId: 'nav-search-container',
            template: (item) => `
                <button type="button" class="w-full px-4 py-2.5 hover:bg-gray-50 flex items-center justify-between group transition-colors suggest-clickable" data-name="${item.name}">
                    <div class="flex items-center gap-2.5">
                        <div class="p-1 bg-gray-100 rounded group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                            <i data-lucide="bike" class="w-3 h-3"></i>
                        </div>
                        <span class="text-xs font-bold text-gray-700 group-hover:text-black">${item.name}</span>
                    </div>
                    <span class="text-[9px] font-black text-blue-500 bg-blue-50 px-1.5 py-0.5 rounded">${item.count.toLocaleString()}台</span>
                </button>
            `
        });
    } catch (e) { console.error("Nav search init failed", e); }
};

// 実行タイミングの制御
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotoHubSuggest);
} else {
    initMotoHubSuggest();
}