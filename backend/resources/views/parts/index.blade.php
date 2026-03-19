<x-layout>
    <x-slot:title>バイクパーツ検索 | MotoHub</x-slot:title>

    <x-slot:metaDescription>
        バイクパーツを楽天市場から一括検索。マフラー・タイヤ・シートなど全カテゴリ対応。
    </x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- ヘッダー --}}
        <section class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-10">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <h1 class="text-2xl sm:text-3xl font-black mb-2">バイクパーツ検索</h1>
                <p class="text-gray-300 text-sm">楽天市場からバイクパーツを横断検索</p>
            </div>
        </section>

        {{-- 検索フォーム --}}
        <section class="max-w-5xl mx-auto px-4 -mt-6">
            <form id="parts-search-form" class="bg-white rounded-xl shadow-lg p-5 sm:p-6">
                {{-- バリデーションエラー --}}
                <div id="form-error" class="hidden mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm font-bold"></div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    {{-- キーワード --}}
                    <div>
                        <label for="keyword" class="block text-xs font-bold text-gray-600 mb-1">キーワード</label>
                        <input type="text" id="keyword" name="keyword" placeholder="例: マフラー、ブレーキパッド"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    </div>
                    {{-- 車種名（サジェスト付き） --}}
                    <div class="relative">
                        <label for="bike" class="block text-xs font-bold text-gray-600 mb-1">車種名</label>
                        <input type="text" id="bike" name="bike" placeholder="例: CBR250RR、YZF-R25" autocomplete="off"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        <ul id="suggest-list" class="hidden absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"></ul>
                    </div>
                    {{-- カテゴリ --}}
                    <div>
                        <label for="category" class="block text-xs font-bold text-gray-600 mb-1">パーツカテゴリ（任意）</label>
                        <select id="category" name="category"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                            <option value="">すべて</option>
                            <option value="マフラー">マフラー</option>
                            <option value="タイヤ">タイヤ</option>
                            <option value="ブレーキ">ブレーキ</option>
                            <option value="シート">シート</option>
                            <option value="ミラー">ミラー</option>
                            <option value="カウル">カウル</option>
                            <option value="チェーン">チェーン</option>
                            <option value="サスペンション">サスペンション</option>
                            <option value="オイル">オイル</option>
                            <option value="バッテリー">バッテリー</option>
                            <option value="ヘルメット">ヘルメット</option>
                            <option value="グローブ">グローブ</option>
                            <option value="ジャケット">ジャケット</option>
                        </select>
                    </div>
                </div>
                <button type="submit" id="search-btn"
                    class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 mx-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    検索する
                </button>
            </form>
        </section>

        {{-- ローディング --}}
        <div id="loading" class="hidden max-w-5xl mx-auto px-4 py-12 text-center">
            <div class="inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-gray-500 text-sm mt-3">検索中...</p>
        </div>

        {{-- エラー表示 --}}
        <div id="error-message" class="hidden max-w-5xl mx-auto px-4 py-6">
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm"></div>
        </div>

        {{-- 検索結果 --}}
        <section id="results" class="hidden max-w-5xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">検索結果 <span id="result-count" class="text-blue-600 text-sm font-normal"></span></h2>
            </div>
            <div id="result-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            </div>

            {{-- もっと見るボタン --}}
            <div id="load-more-wrap" class="hidden text-center mt-8">
                <button id="load-more-btn"
                    class="inline-flex items-center gap-2 bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-bold text-sm px-8 py-3 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="load-more-text">もっと見る</span>
                    <div id="load-more-spinner" class="hidden w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                </button>
            </div>
        </section>

        {{-- 結果0件 --}}
        <div id="no-results" class="hidden max-w-5xl mx-auto px-4 py-12 text-center">
            <div class="text-5xl mb-3">🔍</div>
            <p class="text-gray-500 font-bold">該当するパーツが見つかりませんでした</p>
            <p class="text-gray-400 text-sm mt-1">キーワードを変えて再検索してみてください</p>
        </div>
    </div>

    <x-slot:scripts>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ===== DOM =====
        const form = document.getElementById('parts-search-form');
        const formError = document.getElementById('form-error');
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');
        const resultGrid = document.getElementById('result-grid');
        const resultCount = document.getElementById('result-count');
        const noResults = document.getElementById('no-results');
        const errorMessage = document.getElementById('error-message');
        const searchBtn = document.getElementById('search-btn');
        const loadMoreWrap = document.getElementById('load-more-wrap');
        const loadMoreBtn = document.getElementById('load-more-btn');
        const loadMoreText = document.getElementById('load-more-text');
        const loadMoreSpinner = document.getElementById('load-more-spinner');
        const keywordInput = document.getElementById('keyword');
        const bikeInput = document.getElementById('bike');
        const suggestList = document.getElementById('suggest-list');

        // ===== State =====
        let currentPage = 1;
        let totalCount = 0;
        let totalPages = 0;
        let displayedCount = 0;
        let isLoading = false;
        let lastSearchParams = null;
        let suggestTimer = null;
        let suggestItems = [];
        let suggestIndex = -1;

        // ===== バリデーション =====
        function validateForm() {
            const keyword = keywordInput.value.trim();
            const bike = bikeInput.value.trim();
            if (!keyword && !bike) {
                formError.textContent = 'キーワードまたは車種名を入力してください';
                formError.classList.remove('hidden');
                return false;
            }
            formError.classList.add('hidden');
            return true;
        }

        keywordInput.addEventListener('input', () => {
            if (keywordInput.value.trim() || bikeInput.value.trim()) formError.classList.add('hidden');
        });

        // ===== サジェスト =====
        function renderSuggest(items) {
            suggestItems = items;
            suggestIndex = -1;
            if (items.length === 0) { suggestList.classList.add('hidden'); return; }
            suggestList.innerHTML = items.map((item, i) =>
                `<li class="suggest-item px-3 py-2.5 text-sm cursor-pointer hover:bg-blue-50 border-b border-gray-100 last:border-0" data-index="${i}">
                    <span class="font-bold text-gray-800">${item.name}</span>
                    <span class="text-gray-400 text-xs ml-1">(${item.count}件)</span>
                </li>`
            ).join('');
            suggestList.classList.remove('hidden');
        }

        function selectSuggest(index) {
            if (index < 0 || index >= suggestItems.length) return;
            bikeInput.value = suggestItems[index].name;
            suggestList.classList.add('hidden');
            suggestItems = [];
            suggestIndex = -1;
            if (bikeInput.value.trim()) formError.classList.add('hidden');
        }

        function highlightSuggest(newIndex) {
            const items = suggestList.querySelectorAll('.suggest-item');
            items.forEach(el => el.classList.remove('bg-blue-50'));
            if (newIndex >= 0 && newIndex < items.length) {
                items[newIndex].classList.add('bg-blue-50');
                items[newIndex].scrollIntoView({ block: 'nearest' });
            }
            suggestIndex = newIndex;
        }

        bikeInput.addEventListener('input', () => {
            if (bikeInput.value.trim() || keywordInput.value.trim()) formError.classList.add('hidden');
            const q = bikeInput.value.trim();
            clearTimeout(suggestTimer);
            if (q.length < 1) { suggestList.classList.add('hidden'); return; }

            suggestTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/bikes/suggest?keyword=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    renderSuggest(data.models || []);
                } catch { suggestList.classList.add('hidden'); }
            }, 300);
        });

        bikeInput.addEventListener('keydown', (e) => {
            if (suggestList.classList.contains('hidden') || suggestItems.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightSuggest(suggestIndex < suggestItems.length - 1 ? suggestIndex + 1 : 0);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightSuggest(suggestIndex > 0 ? suggestIndex - 1 : suggestItems.length - 1);
            } else if (e.key === 'Enter' && suggestIndex >= 0) {
                e.preventDefault();
                selectSuggest(suggestIndex);
            } else if (e.key === 'Escape') {
                suggestList.classList.add('hidden');
            }
        });

        suggestList.addEventListener('click', (e) => {
            const li = e.target.closest('.suggest-item');
            if (li) selectSuggest(parseInt(li.dataset.index));
        });

        document.addEventListener('click', (e) => {
            if (!bikeInput.contains(e.target) && !suggestList.contains(e.target)) suggestList.classList.add('hidden');
        });

        // ===== カード描画 =====
        function renderCard(item) {
            const stars = parseFloat(item.review_avg) || 0;
            const fullStars = Math.floor(stars);
            const halfStar = stars - fullStars >= 0.5;
            let starHtml = '';
            for (let i = 0; i < fullStars; i++) starHtml += '<span class="text-yellow-400">&#9733;</span>';
            if (halfStar) starHtml += '<span class="text-yellow-400">&#9733;</span>';
            const empty = 5 - fullStars - (halfStar ? 1 : 0);
            for (let i = 0; i < empty; i++) starHtml += '<span class="text-gray-300">&#9733;</span>';
            const price = Number(item.price).toLocaleString();
            const imageUrl = item.image ? item.image.replace('?_ex=128x128', '?_ex=300x300') : '';
            const card = document.createElement('div');
            card.className = 'bg-white rounded-xl shadow hover:shadow-lg transition-shadow overflow-hidden flex flex-col';
            card.innerHTML = `
                <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                    ${imageUrl
                        ? `<img src="${imageUrl}" alt="${item.name}" class="w-full h-full object-contain p-2" loading="lazy">`
                        : `<div class="text-gray-300 text-4xl">🔧</div>`}
                </div>
                <div class="p-3 flex flex-col flex-grow">
                    <h3 class="text-sm font-bold text-gray-800 line-clamp-2 mb-1">${item.name}</h3>
                    <p class="text-xs text-gray-500 mb-2">${item.shop}</p>
                    <div class="mt-auto">
                        <p class="text-lg font-black text-red-600 mb-1">&yen;${price}</p>
                        <div class="flex items-center gap-1 text-xs mb-3">
                            ${starHtml}
                            <span class="text-gray-500 ml-1">(${item.review_count}件)</span>
                        </div>
                        <a href="${item.url}" target="_blank" rel="noopener noreferrer"
                            class="block text-center bg-red-500 hover:bg-red-600 text-white text-xs font-bold py-2 rounded-lg transition-colors">
                            楽天市場で見る
                        </a>
                    </div>
                </div>`;
            return card;
        }

        function updateResultCount() {
            resultCount.textContent = `${totalCount.toLocaleString()} 件中 ${displayedCount} 件を表示中`;
        }

        function updateLoadMoreButton(hasMore) {
            if (hasMore) {
                const remaining = totalCount - displayedCount;
                loadMoreText.textContent = `もっと見る（残り ${remaining.toLocaleString()} 件）`;
                loadMoreWrap.classList.remove('hidden');
            } else {
                loadMoreWrap.classList.add('hidden');
            }
        }

        function showError(msg) {
            errorMessage.querySelector('div').textContent = msg;
            errorMessage.classList.remove('hidden');
        }

        // ===== API通信 =====
        async function fetchResults(page, append) {
            if (isLoading) return;
            isLoading = true;
            const params = new URLSearchParams(lastSearchParams.toString());
            params.set('page', page);
            try {
                const res = await fetch(`/parts/search?${params.toString()}`);
                const data = await res.json();
                if (data.error) {
                    showError(append ? '読み込みに失敗しました。再度お試しください。' : data.error);
                    return;
                }
                const items = data.items || [];
                if (!append && items.length === 0) { noResults.classList.remove('hidden'); return; }
                currentPage = data.currentPage;
                totalCount = data.totalCount;
                totalPages = data.totalPages;
                if (!append) { resultGrid.innerHTML = ''; displayedCount = 0; }
                items.forEach(item => resultGrid.appendChild(renderCard(item)));
                displayedCount += items.length;
                updateResultCount();
                updateLoadMoreButton(data.hasMore);
                results.classList.remove('hidden');
            } catch (err) {
                showError(append ? '読み込みに失敗しました。再度お試しください。' : '通信エラーが発生しました。もう一度お試しください。');
            } finally {
                isLoading = false;
            }
        }

        // ===== 検索フォーム送信 =====
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!validateForm()) return;

            const keyword = keywordInput.value.trim();
            const bike = bikeInput.value.trim();
            const category = document.getElementById('category').value;
            const params = new URLSearchParams();
            if (keyword) params.set('keyword', keyword);
            if (bike) params.set('bike', bike);
            if (category) params.set('category', category);

            loading.classList.remove('hidden');
            results.classList.add('hidden');
            noResults.classList.add('hidden');
            errorMessage.classList.add('hidden');
            loadMoreWrap.classList.add('hidden');
            suggestList.classList.add('hidden');
            searchBtn.disabled = true;
            searchBtn.classList.add('opacity-50');

            currentPage = 1; totalCount = 0; totalPages = 0; displayedCount = 0;
            lastSearchParams = params;
            await fetchResults(1, false);

            loading.classList.add('hidden');
            searchBtn.disabled = false;
            searchBtn.classList.remove('opacity-50');
        });

        // ===== もっと見る =====
        loadMoreBtn.addEventListener('click', async () => {
            if (isLoading || !lastSearchParams) return;
            loadMoreBtn.disabled = true;
            loadMoreText.classList.add('hidden');
            loadMoreSpinner.classList.remove('hidden');
            errorMessage.classList.add('hidden');
            await fetchResults(currentPage + 1, true);
            loadMoreBtn.disabled = false;
            loadMoreText.classList.remove('hidden');
            loadMoreSpinner.classList.add('hidden');
        });

        // ===== URLパラメータから自動検索 =====
        const urlParams = new URLSearchParams(window.location.search);
        const urlKeyword = urlParams.get('keyword');
        const urlBike = urlParams.get('bike');
        if (urlKeyword) keywordInput.value = urlKeyword;
        if (urlBike) bikeInput.value = urlBike;
        if (urlKeyword || urlBike) {
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
    </script>
    </x-slot:scripts>
</x-layout>
