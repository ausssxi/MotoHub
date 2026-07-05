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

                        {{-- 記事CTA（記事がある場合のみ・該当セクションへ直行アンカー） --}}
                        <template x-if="card?.article">
                            <a :href="card.article + (card.article_anchor ? '#' + card.article_anchor : '')"
                               @click="trackCta('article')"
                               class="mt-4 w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-4 py-3.5 rounded-xl transition active:scale-[0.99]">
                                くわしい対処法・直し方を読む
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </template>

                        {{-- 整備・修理店CTA（「店に出す」系の判定のときのみ） --}}
                        <template x-if="['shop','check_then_shop','diy_then_shop'].includes(card?.verdict)">
                            <div class="mt-3">
                                <a href="{{ route('shops.repair.index') }}"
                                   @click="trackCta('shop')"
                                   class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-black text-sm px-4 py-3.5 rounded-xl transition active:scale-[0.99]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                                    近くの整備・修理店を探す
                                </a>
                                <a href="{{ route('shops.submit.create', ['type' => 'repair_only']) }}"
                                   @click="trackCta('submit_shop')"
                                   class="mt-2 block text-center text-[11px] font-bold text-gray-400 hover:text-green-600 transition-colors">
                                    近くの整備・修理店が載っていない → お店を教える
                                </a>
                            </div>
                        </template>

                        {{-- パーツ価格比較CTA（原因に売れる部品があるときだけ・記事/店CTAより下位） --}}
                        <template x-if="card?.parts_keyword">
                            <a :href="@js(route('parts.compare')) + '?keyword=' + encodeURIComponent(card.parts_keyword)"
                               @click="trackCta('parts')"
                               class="mt-3 w-full flex items-center justify-center gap-2 bg-white border-2 border-orange-400 text-orange-600 hover:bg-orange-50 font-black text-sm px-4 py-3.5 rounded-xl transition active:scale-[0.99]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                                <span x-text="card.parts_label"></span>
                                <span class="text-[10px] font-bold text-orange-400">楽天/Yahoo/Amazon</span>
                            </a>
                        </template>

                        {{-- 型番（適合表）ページへの導線（fitment_task があり公開車種があるときだけ） --}}
                        <template x-if="card?.fitment_task && fitModels(card.fitment_task).length > 0">
                            <div class="mt-3">
                                {{-- A) 個人化: ログイン＆マイバイクが公開車種に一致 --}}
                                <template x-if="userBikes(card.fitment_task).length > 0">
                                    <div class="space-y-2">
                                        <template x-for="b in userBikes(card.fitment_task).slice(0,3)" :key="b.slug">
                                            <a :href="fitmentUrl(b.slug, card.fitment_task)"
                                               @click="trackCta('fitment')"
                                               class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-4 py-3.5 rounded-xl transition active:scale-[0.99]">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                                                あなたの<span x-text="b.display_name"></span>の適合バッテリーを見る
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                {{-- B) 未ログイン／不一致: メーカー別 optgroup の select --}}
                                <template x-if="userBikes(card.fitment_task).length === 0">
                                    <div class="bg-white border-2 border-blue-200 rounded-xl p-4">
                                        <p class="text-sm font-black text-gray-800 mb-2">お使いの車種の適合型番を調べる</p>
                                        <div class="flex gap-2">
                                            <select x-model="fitSlug" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                                <option value="">車種を選択</option>
                                                <template x-for="(models, maker) in fitGroups(card.fitment_task)" :key="maker">
                                                    <optgroup :label="maker">
                                                        <template x-for="m in models" :key="m.slug">
                                                            <option :value="m.slug" x-text="m.name"></option>
                                                        </template>
                                                    </optgroup>
                                                </template>
                                            </select>
                                            <button type="button" @click="goFitment(card.fitment_task)"
                                                    class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-5 py-2.5 rounded-lg transition active:scale-[0.99]">見る</button>
                                        </div>
                                    </div>
                                </template>

                                {{-- C) 末尾一行 --}}
                                <p class="text-[11px] text-gray-400 mt-2 leading-relaxed">
                                    一覧にない車種は、現在お使いのバッテリー本体の品番表記をご確認ください。
                                </p>
                            </div>
                        </template>

                        {{-- 解決フィードバック（CTA群の下・控えめ・1セッション1回） --}}
                        <div class="mt-5 pt-4 border-t border-gray-100" x-data="{ done: false }">
                            <template x-if="!done">
                                <div class="text-center">
                                    <p class="text-xs font-bold text-gray-500 mb-2">この診断で解決できましたか？</p>
                                    <div class="flex gap-2 justify-center">
                                        <button type="button" @click="feedback('yes'); done = true"
                                            class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-700 border border-gray-200 hover:border-emerald-300 rounded-lg px-3 py-2 transition">
                                            👍 解決した・できそう
                                        </button>
                                        <button type="button" @click="feedback('no'); done = true"
                                            class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 bg-gray-50 hover:bg-rose-50 hover:text-rose-700 border border-gray-200 hover:border-rose-300 rounded-lg px-3 py-2 transition">
                                            👎 解決しなかった
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="done">
                                <p class="text-xs font-bold text-emerald-600 text-center">フィードバックありがとうございます</p>
                            </template>
                        </div>
                    </div>

                    {{-- リーチフック（会員登録／再診断）。push通知はv1では出さない --}}
                    <div class="border-t border-gray-100 bg-gray-50 p-5 sm:p-6 space-y-3">
                        <a href="{{ route('register') }}"
                           @click="trackCta('register')"
                           class="w-full flex items-center justify-center gap-2 bg-white border border-gray-200 hover:border-blue-300 hover:text-blue-700 text-gray-800 font-bold text-sm px-4 py-3 rounded-xl transition">
                            <span>🔖</span> 無料会員登録して診断結果を保存する
                        </a>
                        <button type="button" @click="trackCta('retry'); reset()"
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
        window.__troubleTrackUrl = @js(route('trouble.track'));
        window.__troubleCsrf = @js(csrf_token());
        window.__troubleFitment = {
            models: @json($fitmentModels),
            userBikes: @json($userFitmentBikes),
            urlTemplate: @js(route('fitments.show', ['bikeModel' => '__SLUG__', 'task' => '__TASK__'])),
        };

        function troubleTool() {
            return {
                cfg: window.__troubleCfg,
                fit: window.__troubleFitment,
                fitSlug: '',
                phase: 'select',     // 'select' | 'question' | 'result'
                symptomKey: null,
                stack: [],           // 辿ったノードidの履歴（戻る用）
                cardKey: null,

                init() {
                    // ?symptom=<スラッグ> でディープリンク（記事/広告から直接症状へ）。?s= は後方互換。
                    const params = new URLSearchParams(window.location.search);
                    const slug = params.get('symptom') || params.get('s');
                    if (slug && this.cfg.symptoms[slug]) {
                        this.pickSymptom(slug, 'deeplink');
                    }
                },

                get symptom() { return this.symptomKey ? this.cfg.symptoms[this.symptomKey] : null; },
                get currentNode() {
                    const id = this.stack[this.stack.length - 1];
                    return id ? this.cfg.nodes[id] : null;
                },
                get card() { return this.cardKey ? this.cfg.cards[this.cardKey] : null; },
                get verdict() { return this.card ? this.cfg.verdicts[this.card.verdict] : null; },

                // ── 計測（fire-and-forget・UIを一切ブロックしない）──
                _sid: null,
                sessionId() {
                    if (this._sid) return this._sid;
                    let s = null;
                    try { s = sessionStorage.getItem('trouble_sid'); } catch (e) {}
                    if (!s) {
                        const hex = '0123456789abcdef';
                        s = '';
                        for (let i = 0; i < 36; i++) {
                            s += (i === 8 || i === 13 || i === 18 || i === 23) ? '-' : hex[(Math.random() * 16) | 0];
                        }
                        try { sessionStorage.setItem('trouble_sid', s); } catch (e) {}
                    }
                    this._sid = s;
                    return s;
                },
                track(event, extra) {
                    try {
                        const fd = new FormData();
                        fd.append('_token', window.__troubleCsrf || '');
                        fd.append('session_id', this.sessionId());
                        fd.append('event', event);
                        Object.entries(extra || {}).forEach(([k, v]) => { if (v != null) fd.append(k, v); });
                        const url = window.__troubleTrackUrl;
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(url, fd);
                        } else {
                            fetch(url, { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' }).catch(() => {});
                        }
                    } catch (e) { /* 計測失敗は診断に影響させない */ }
                },
                trackCta(cta) {
                    this.track('cta_clicked', { symptom: this.symptomKey, card: this.cardKey, verdict: this.card ? this.card.verdict : null, cta: cta });
                },
                feedback(answer) {
                    this.track('feedback', { symptom: this.symptomKey, card: this.cardKey, verdict: this.card ? this.card.verdict : null, answer: answer });
                },

                // ── 型番（適合表）導線 ──
                fitModels(task) { return (this.fit && this.fit.models && this.fit.models[task]) || []; },
                userBikes(task) { return (this.fit && this.fit.userBikes && this.fit.userBikes[task]) || []; },
                fitGroups(task) {
                    const groups = {};
                    this.fitModels(task).forEach(m => { (groups[m.maker_name] = groups[m.maker_name] || []).push(m); });
                    return groups;
                },
                fitmentUrl(slug, task) {
                    return this.fit.urlTemplate.replace('__SLUG__', encodeURIComponent(slug)).replace('__TASK__', encodeURIComponent(task));
                },
                goFitment(task) {
                    if (!this.fitSlug) return;
                    this.trackCta('fitment');
                    window.location.href = this.fitmentUrl(this.fitSlug, task);
                },

                pickSymptom(key, source) {
                    this.symptomKey = key;
                    this.stack = [this.cfg.symptoms[key].root];
                    this.cardKey = null;
                    this.phase = 'question';
                    this.track('symptom_selected', { symptom: key, source: source || null });
                    this.toTop();
                },
                choose(opt) {
                    const stepId = this.stack[this.stack.length - 1];
                    this.track('step_answered', { symptom: this.symptomKey, step: stepId, answer: opt.card || opt.next || null });
                    if (opt.card) {
                        this.cardKey = opt.card;
                        this.phase = 'result';
                        const c = this.cfg.cards[opt.card];
                        this.track('verdict_shown', { symptom: this.symptomKey, step: opt.card, card: opt.card, verdict: c ? c.verdict : null });
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
