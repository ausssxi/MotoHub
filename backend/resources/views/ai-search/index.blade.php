<x-layout>
    <x-slot:title>{{ $title }}</x-slot:title>
    <x-slot:metaDescription>{{ $metaDescription }}</x-slot:metaDescription>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="min-h-screen bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950"
         x-data="aiSearch()" x-cloak>

        {{-- Hero --}}
        <section class="relative overflow-hidden py-12 md:py-16" x-show="!result || loading">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 left-1/4 w-72 h-72 bg-blue-500 rounded-full blur-3xl"></div>
                <div class="absolute bottom-10 right-1/4 w-72 h-72 bg-purple-500 rounded-full blur-3xl"></div>
            </div>
            <div class="relative max-w-3xl mx-auto px-4 text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-3">
                    AIで探す
                </h1>
                <p class="text-gray-400 mb-8">
                    自然言語で検索条件を入力するだけ。AIがあなたにぴったりのバイクを見つけます。
                </p>

                {{-- Search bar --}}
                <form @submit.prevent="doSearch()" class="relative max-w-2xl mx-auto">
                    <input type="text"
                           x-model="query"
                           x-ref="mainInput"
                           placeholder="例: 予算30万円、125cc、神奈川で探したい"
                           class="w-full pl-5 pr-14 py-4 rounded-xl bg-gray-800/80 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-base"
                           style="color: #333;"
                           :disabled="loading"
                           maxlength="200">
                    <button type="submit"
                            class="absolute right-2 top-1/2 -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white p-2.5 rounded-lg transition disabled:opacity-50"
                            :disabled="loading || !query.trim()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                </form>

                {{-- Preset buttons --}}
                <div class="flex flex-wrap justify-center gap-2 mt-5">
                    <template x-for="preset in presets" :key="preset">
                        <button @click="query = preset; doSearch()"
                                class="px-3 py-1.5 rounded-full text-sm bg-gray-800 text-gray-300 border border-gray-700 hover:border-blue-500 hover:text-blue-400 transition"
                                :disabled="loading"
                                x-text="preset"></button>
                    </template>
                </div>
            </div>
        </section>

        {{-- Loading --}}
        <div x-show="loading" class="text-center py-12">
            <div class="inline-flex items-center gap-3 text-gray-400">
                <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="loadingText">AIが検索条件を分析中...</span>
            </div>
        </div>

        {{-- Error --}}
        <div x-show="error && !loading" class="max-w-2xl mx-auto px-4 py-8">
            <div class="bg-red-900/30 border border-red-800 rounded-xl p-6 text-center">
                <p class="text-red-300" x-text="error"></p>
                <button @click="error = null" class="mt-3 text-sm text-gray-400 hover:text-white underline">閉じる</button>
            </div>
        </div>

        {{-- Results --}}
        <section x-show="result && !loading" class="max-w-5xl mx-auto px-4 pb-16 pt-8" x-transition>

            {{-- Conditions summary (compact) --}}
            <div class="flex flex-wrap items-center gap-2 mb-6" x-show="conditionTags.length > 0">
                <span class="text-xs text-gray-500">検索条件:</span>
                <template x-for="tag in conditionTags" :key="tag">
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs bg-blue-900/50 text-blue-300 border border-blue-800"
                          x-text="tag"></span>
                </template>
            </div>

            {{-- ============================================ --}}
            {{-- CONSULT layout: advice first, cards secondary --}}
            {{-- ============================================ --}}
            <template x-if="result?.query_type === 'consult'">
                <div>
                    {{-- AI Advice (large, prominent) --}}
                    <div x-show="result?.advice" class="bg-gray-800 border-l-4 border-blue-500 rounded-xl p-6 md:p-8 mb-8">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-white">AIからの回答</h3>
                        </div>
                        <div class="ai-advice-content text-gray-200 text-base leading-relaxed"
                             x-html="renderMarkdown(result?.advice || '')"></div>
                    </div>

                    {{-- Reference bikes (small) --}}
                    <div x-show="result?.results?.length > 0" class="mb-8">
                        <h4 class="text-sm font-bold text-gray-400 mb-3">参考車両</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <template x-for="bike in result.results" :key="bike.id">
                                <a :href="'/bikes/' + bike.id"
                                   class="flex items-center gap-3 bg-gray-800/60 border border-gray-700 rounded-lg p-3 hover:border-blue-500 transition group">
                                    <img :src="bike.images?.[0] || '/images/no-image.svg'"
                                         :alt="bike.name"
                                         class="w-16 h-12 object-cover rounded flex-shrink-0"
                                         loading="lazy"
                                         onerror="this.src='/images/no-image.svg'">
                                    <div class="min-w-0">
                                        <p class="text-white text-xs font-bold truncate group-hover:text-blue-400 transition" x-text="bike.name"></p>
                                        <p class="text-red-500 text-sm font-bold"><span x-text="bike.total_price"></span>万円</p>
                                    </div>
                                </a>
                            </template>
                        </div>
                        <div class="mt-3" x-show="result?.total_count > 3">
                            <a :href="result?.search_url || '/bikes/search'"
                               class="text-blue-400 hover:text-blue-300 text-sm">
                                全 <span x-text="result?.total_count?.toLocaleString()"></span> 件を見る &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ============================================ --}}
            {{-- SEARCH layout: cards first, advice secondary --}}
            {{-- ============================================ --}}
            <template x-if="result?.query_type !== 'consult'">
                <div>
                    {{-- AI Advice (medium) --}}
                    <div x-show="result?.advice" class="bg-gray-800 border-l-4 border-blue-500 rounded-xl p-5 md:p-6 mb-6">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-blue-400 mb-2">AIからのアドバイス</h3>
                                <div class="ai-advice-content text-gray-200 text-base leading-relaxed"
                                     x-html="renderMarkdown(result?.advice || '')"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Result count + Refine buttons --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <p class="text-gray-400 text-sm">
                            <span class="text-white font-bold text-lg" x-text="result?.total_count?.toLocaleString()"></span> 件ヒット
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button @click="followUp('走行5000km以下に絞って')"
                                    class="px-3 py-1 rounded-full text-xs bg-gray-800 text-gray-400 border border-gray-700 hover:border-green-500 hover:text-green-400 transition"
                                    :disabled="loading">走行少なめ</button>
                            <button @click="followUp('2020年以降に絞って')"
                                    class="px-3 py-1 rounded-full text-xs bg-gray-800 text-gray-400 border border-gray-700 hover:border-green-500 hover:text-green-400 transition"
                                    :disabled="loading">高年式</button>
                            <button @click="followUp('ホンダに絞って')"
                                    class="px-3 py-1 rounded-full text-xs bg-gray-800 text-gray-400 border border-gray-700 hover:border-green-500 hover:text-green-400 transition"
                                    :disabled="loading">Honda</button>
                            <button @click="followUp('ヤマハに絞って')"
                                    class="px-3 py-1 rounded-full text-xs bg-gray-800 text-gray-400 border border-gray-700 hover:border-green-500 hover:text-green-400 transition"
                                    :disabled="loading">Yamaha</button>
                        </div>
                    </div>

                    {{-- Bike cards --}}
                    <div x-show="result?.results?.length > 0">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <template x-for="bike in result.results" :key="bike.id">
                                <a :href="'/bikes/' + bike.id"
                                   class="block bg-gray-800 border border-gray-700 rounded-xl overflow-hidden hover:border-blue-500 hover:shadow-lg hover:shadow-blue-500/10 transition group">
                                    <div class="aspect-[4/3] bg-gray-900 relative overflow-hidden">
                                        <img :src="bike.images?.[0] || '/images/no-image.svg'"
                                             :alt="bike.name"
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                             loading="lazy"
                                             onerror="this.src='/images/no-image.svg'">
                                        <span x-show="bike.bargain_info?.is_bargain"
                                              class="absolute top-2 left-2 px-2 py-0.5 rounded text-xs font-bold bg-red-600 text-white">
                                            お買い得
                                        </span>
                                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded text-xs bg-gray-900/80 text-gray-300"
                                              x-text="bike.source"></span>
                                    </div>
                                    <div class="p-4">
                                        <h3 class="text-white font-bold text-sm mb-2 line-clamp-2 group-hover:text-blue-400 transition"
                                            x-text="bike.name"></h3>
                                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-400 mb-3">
                                            <div><span class="text-gray-500">年式:</span> <span x-text="bike.model_year"></span></div>
                                            <div><span class="text-gray-500">走行:</span> <span x-text="bike.mileage"></span></div>
                                            <div><span class="text-gray-500">排気量:</span> <span x-text="bike.displacement"></span></div>
                                            <div><span class="text-gray-500">修復歴:</span> <span x-text="bike.repair_history"></span></div>
                                        </div>
                                        <div class="flex items-end justify-between">
                                            <div>
                                                <span class="text-red-500 text-xl font-bold" x-text="bike.total_price"></span>
                                                <span class="text-red-500 text-sm">万円</span>
                                            </div>
                                            <span class="text-xs text-gray-500 truncate max-w-[120px]" x-text="bike.prefecture"></span>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>

                        <div class="text-center mt-8" x-show="result?.total_count > 3">
                            <a :href="result?.search_url || '/bikes/search'"
                               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium transition">
                                <span>全 <span x-text="result?.total_count?.toLocaleString()"></span> 件を見る</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- No results --}}
                    <div x-show="result && result.results?.length === 0" class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-800 mb-4">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-gray-400 mb-2">条件に合うバイクが見つかりませんでした</p>
                        <p class="text-gray-500 text-sm mb-4">条件を変えて再検索してみてください</p>
                        <a href="/bikes/search" class="text-blue-400 hover:text-blue-300 underline text-sm">通常検索で探す</a>
                    </div>
                </div>
            </template>

            {{-- ============================================ --}}
            {{-- Follow-up input (both layouts)               --}}
            {{-- ============================================ --}}
            <div class="mt-10 max-w-2xl mx-auto">
                <form @submit.prevent="doSearch()" class="relative">
                    <input type="text"
                           x-model="query"
                           x-ref="followUpInput"
                           placeholder="続けて質問...（例: 維持費の比較は？ / もう少し安いのは？）"
                           class="w-full pl-5 pr-14 py-3.5 rounded-xl bg-gray-800/80 border border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                           style="color: #333;"
                           :disabled="loading"
                           maxlength="200">
                    <button type="submit"
                            class="absolute right-2 top-1/2 -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-lg transition disabled:opacity-50"
                            :disabled="loading || !query.trim()">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </form>
                <p class="text-gray-600 text-xs mt-2 text-center">会話の文脈を引き継いで追加質問できます</p>
            </div>
        </section>
    </div>

    <x-slot:scripts>
        <script>
            function aiSearch() {
                var STORAGE_KEY = 'aiSearchState';
                return {
                    query: '',
                    loading: false,
                    loadingText: 'AIが検索条件を分析中...',
                    error: null,
                    result: null,
                    conversationHistory: [],
                    presets: [
                        '予算30万円、125ccの原付二種',
                        '東京でホンダのネイキッド',
                        '50万円以下で250ccのスポーツ',
                        'CBR250RRとNinja250、どっちがいい？',
                        '大阪で400ccのアメリカン',
                        '初心者向け、20万円以下'
                    ],

                    init() {
                        try {
                            var saved = sessionStorage.getItem(STORAGE_KEY);
                            if (saved) {
                                var state = JSON.parse(saved);
                                this.result = state.result || null;
                                this.conversationHistory = state.conversationHistory || [];
                            }
                        } catch (e) {}
                    },

                    saveState() {
                        try {
                            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                                result: this.result,
                                conversationHistory: this.conversationHistory
                            }));
                        } catch (e) {}
                    },

                    get conditionTags() {
                        if (!this.result?.conditions) return [];
                        const c = this.result.conditions;
                        const tags = [];
                        if (c.max_price) tags.push('~' + (c.max_price / 10000).toLocaleString() + '万円');
                        if (c.min_price) tags.push((c.min_price / 10000).toLocaleString() + '万円~');
                        if (c.max_displacement) tags.push('~' + c.max_displacement + 'cc');
                        if (c.min_displacement) tags.push(c.min_displacement + 'cc~');
                        if (c.max_mileage) tags.push('~' + c.max_mileage.toLocaleString() + 'km');
                        if (c.min_model_year) tags.push(c.min_model_year + '年~');
                        if (c.prefecture) tags.push(c.prefecture);
                        if (c.manufacturer) tags.push(c.manufacturer);
                        if (c.model_name) tags.push(c.model_name);
                        return tags;
                    },

                    renderMarkdown(text) {
                        if (!text) return '';
                        let html = text
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                            .replace(/\*\*(.+?)\*\*/g, '<strong class="text-white font-bold">$1</strong>')
                            .replace(/^- (.+)$/gm, '<li>$1</li>')
                            .replace(/(<li>.*<\/li>\n?)+/gs, function(m) { return '<ul class="list-disc list-inside space-y-1 my-2">' + m + '</ul>'; })
                            .replace(/\n{2,}/g, '</p><p class="mt-3">')
                            .replace(/\n/g, '<br>');
                        return '<p>' + html + '</p>';
                    },

                    async doSearch() {
                        if (!this.query.trim() || this.loading) return;

                        const currentQuery = this.query.trim();
                        this.loading = true;
                        this.error = null;
                        this.result = null;
                        this.loadingText = 'AIが検索条件を分析中...';

                        setTimeout(() => { if (this.loading) this.loadingText = 'データベースを検索中...'; }, 3000);
                        setTimeout(() => { if (this.loading) this.loadingText = 'おすすめを選定中...'; }, 6000);

                        try {
                            const res = await fetch('/api/ai-search', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                body: JSON.stringify({
                                    query: currentQuery,
                                    history: this.conversationHistory.slice(-10)
                                })
                            });

                            if (res.status === 429) {
                                this.error = '本日の検索回数上限（10回）に達しました。明日またお試しください。';
                                return;
                            }

                            const data = await res.json();

                            if (!res.ok) {
                                this.error = data.error || '検索に失敗しました。';
                                return;
                            }

                            this.conversationHistory.push({ role: 'user', content: currentQuery });
                            this.conversationHistory.push({ role: 'assistant', content: data.advice || '' });

                            this.result = data;
                            this.query = '';
                            this.saveState();

                            this.$nextTick(() => {
                                if (this.$refs.followUpInput) {
                                    this.$refs.followUpInput.focus();
                                }
                            });
                        } catch (e) {
                            this.error = '通信エラーが発生しました。もう一度お試しください。';
                        } finally {
                            this.loading = false;
                        }
                    },

                    followUp(text) {
                        this.query = text;
                        this.doSearch();
                    }
                };
            }
        </script>

        <style>
            .ai-advice-content ul { margin-top: 0.5rem; margin-bottom: 0.5rem; }
            .ai-advice-content li { margin-bottom: 0.25rem; }
            .ai-advice-content p + p { margin-top: 0.75rem; }
            .ai-advice-content strong { color: #fff; }
        </style>

        {{-- JSON-LD --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebApplication",
            "name": "MotoHub AIで探す",
            "description": "自然言語でバイクを検索。予算・排気量・エリアなどを自由に入力するだけで、AIがあなたにぴったりの中古バイクを提案します。",
            "url": "{{ url('/ai-search') }}",
            "applicationCategory": "SearchApplication",
            "operatingSystem": "Web"
        }
        </script>
    </x-slot:scripts>
</x-layout>
