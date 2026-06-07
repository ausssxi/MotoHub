<x-layout>
    <x-slot:title>バイクの症状診断｜トラブルの原因を3ステップで切り分け | MotoHub</x-slot:title>

    <x-slot:metaDescription>
        「エンジンがかからない」「エンストする」「加速しない」など、原付・バイクの症状から原因を切り分ける無料の診断ツール。質問に答えるだけで、自分で直せるか・店に出すべきかの目安と対処法がわかります。
    </x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <style>[x-cloak]{display:none!important}</style>

    <div class="bg-gray-50 min-h-screen">
        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-10 pb-12 px-4">
            <div class="max-w-2xl mx-auto text-center">
                <div class="text-4xl mb-3">🔧</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-2">バイクの症状診断</h1>
                <p class="text-blue-200 text-sm font-bold">症状を選んで質問に答えるだけ。原因の見当と「自分で直す／店に出す」の目安がわかります。</p>
                <div class="mt-5 flex items-center justify-center gap-5 text-[11px] text-blue-300/80 font-bold">
                    <span>⚡ 約30秒</span>
                    <span>🆓 登録不要・無料</span>
                    <span>🛡️ AIなし・事前監修</span>
                </div>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-4 py-8 -mt-6" x-data="troubleTool()" x-cloak>

            {{-- ① 症状を選ぶ --}}
            <div x-show="phase === 'select'" x-transition.opacity>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                        <span class="text-blue-600">Q.</span> どんな症状ですか？
                    </h2>
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="(s, key) in cfg.symptoms" :key="key">
                            <button type="button" @click="pickSymptom(key)"
                                class="group flex items-center gap-3 text-left rounded-xl border border-gray-200 bg-gray-50 hover:bg-blue-50 hover:border-blue-300 active:scale-[0.98] transition px-4 py-4">
                                <span class="text-2xl flex-shrink-0" x-text="s.emoji"></span>
                                <span class="font-bold text-sm text-gray-800 group-hover:text-blue-700 leading-snug" x-text="s.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-4 text-center leading-relaxed">
                    ※ 一般的な原因の切り分けを補助するものです。実際の故障判断・整備は販売店や整備士にご相談ください。
                </p>
            </div>

            {{-- ② 質問（決定木） --}}
            <div x-show="phase === 'question'" x-transition.opacity>
                <button type="button" @click="back()"
                    class="inline-flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-gray-700 mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                    戻る
                </button>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <div class="flex items-center gap-2 mb-1 text-[11px] font-black uppercase tracking-widest text-blue-500">
                        <span x-text="symptom?.emoji"></span>
                        <span x-text="symptom?.label"></span>
                    </div>
                    <h2 class="text-lg font-black text-gray-900 mb-3 leading-snug" x-text="currentNode?.question"></h2>
                    <p x-show="currentNode?.help" class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2 mb-4 leading-relaxed" x-text="currentNode?.help"></p>

                    <div class="space-y-2.5">
                        <template x-for="(opt, i) in currentNode?.options" :key="i">
                            <button type="button" @click="choose(opt)"
                                class="w-full flex items-center justify-between gap-3 text-left rounded-xl border border-gray-200 bg-white hover:bg-blue-50 hover:border-blue-300 active:scale-[0.99] transition px-4 py-3.5">
                                <span class="font-bold text-sm text-gray-800 leading-snug" x-text="opt.label"></span>
                                <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ③ 結果カード --}}
            <div x-show="phase === 'result'" x-transition.opacity>
                <button type="button" @click="back()"
                    class="inline-flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-gray-700 mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                    質問に戻る
                </button>

                <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
                    <div class="p-5 sm:p-6">
                        <span class="inline-block text-[11px] font-black px-3 py-1 rounded-full ring-1 mb-3"
                              :class="verdict?.class" x-text="verdict?.label"></span>
                        <h2 class="text-xl font-black text-gray-900 mb-1">考えられる原因</h2>
                        <p class="text-lg font-bold text-blue-700 mb-3" x-text="card?.cause"></p>
                        <p class="text-sm text-gray-700 leading-relaxed bg-blue-50/60 rounded-xl px-4 py-3" x-text="card?.advice"></p>

                        {{-- 記事CTA（記事がある場合のみ） --}}
                        <template x-if="card?.article">
                            <a :href="card.article"
                               class="mt-4 w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-4 py-3.5 rounded-xl transition active:scale-[0.99]">
                                くわしい対処法・直し方を読む
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </template>
                    </div>

                    {{-- リーチフック（会員登録／再診断）。push通知はv1では出さない --}}
                    <div class="border-t border-gray-100 bg-gray-50 p-5 sm:p-6 space-y-3">
                        <a href="{{ route('register') }}"
                           class="w-full flex items-center justify-center gap-2 bg-white border border-gray-200 hover:border-blue-300 hover:text-blue-700 text-gray-800 font-bold text-sm px-4 py-3 rounded-xl transition">
                            <span>🔖</span> 無料会員登録して診断結果を保存する
                        </a>
                        <button type="button" @click="reset()"
                            class="w-full flex items-center justify-center gap-2 text-gray-500 hover:text-gray-800 font-bold text-sm px-4 py-3 rounded-xl transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg>
                            別の症状をもう一度診断する
                        </button>
                    </div>
                </div>

                <p class="text-[11px] text-gray-400 mt-4 text-center leading-relaxed">
                    ※ 診断結果は一般的な目安です。安全に関わる箇所・判断に迷う場合は必ず販売店や整備士にご相談ください。
                </p>
            </div>
        </div>
    </div>

    <script>
        window.__troubleCfg = {
            symptoms: @json($symptoms),
            nodes:    @json($nodes),
            cards:    @json($cards),
            verdicts: @json($verdicts),
        };

        function troubleTool() {
            return {
                cfg: window.__troubleCfg,
                phase: 'select',     // 'select' | 'question' | 'result'
                symptomKey: null,
                stack: [],           // 辿ったノードidの履歴（戻る用）
                cardKey: null,

                init() {
                    // ?s=<症状キー> でディープリンク（記事や広告から直接症状へ）
                    const s = new URLSearchParams(window.location.search).get('s');
                    if (s && this.cfg.symptoms[s]) {
                        this.pickSymptom(s);
                    }
                },

                get symptom() { return this.symptomKey ? this.cfg.symptoms[this.symptomKey] : null; },
                get currentNode() {
                    const id = this.stack[this.stack.length - 1];
                    return id ? this.cfg.nodes[id] : null;
                },
                get card() { return this.cardKey ? this.cfg.cards[this.cardKey] : null; },
                get verdict() { return this.card ? this.cfg.verdicts[this.card.verdict] : null; },

                pickSymptom(key) {
                    this.symptomKey = key;
                    this.stack = [this.cfg.symptoms[key].root];
                    this.cardKey = null;
                    this.phase = 'question';
                    this.toTop();
                },
                choose(opt) {
                    if (opt.card) {
                        this.cardKey = opt.card;
                        this.phase = 'result';
                        if (typeof gtag === 'function') {
                            gtag('event', 'trouble_result', { symptom: this.symptomKey, card: opt.card });
                        }
                    } else if (opt.next) {
                        this.stack.push(opt.next);
                    }
                    this.toTop();
                },
                back() {
                    if (this.phase === 'result') {
                        this.phase = 'question';
                        this.cardKey = null;
                    } else if (this.stack.length > 1) {
                        this.stack.pop();
                    } else {
                        this.reset();
                        return;
                    }
                    this.toTop();
                },
                reset() {
                    this.phase = 'select';
                    this.symptomKey = null;
                    this.stack = [];
                    this.cardKey = null;
                    this.toTop();
                },
                toTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); },
            };
        }
    </script>
</x-layout>
