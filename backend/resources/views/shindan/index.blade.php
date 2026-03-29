<x-layout>
    <x-slot:title>バイク相性診断 - あなたにピッタリの1台を見つけよう | MotoHub</x-slot:title>

    <x-slot:metaDescription>
        5つの質問に答えるだけで、あなたにピッタリのバイクを診断。ライダータイプと最適な車種をMotoHubがマッチング。結果をXでシェアしよう。
    </x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div id="shindan-app" class="min-h-screen bg-gray-950 text-white overflow-hidden">

        {{-- ===================== START画面 ===================== --}}
        <section id="screen-start" class="shindan-screen min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4 relative">
            {{-- 背景エフェクト --}}
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px] animate-pulse"></div>
                <div class="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-indigo-600/10 rounded-full blur-[100px] animate-pulse" style="animation-delay: 1s;"></div>
                <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>

            <div class="relative z-10 text-center max-w-md w-full">
                {{-- メインロゴ --}}
                <div class="mb-8">
                    <div class="w-24 h-24 mx-auto rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-2xl shadow-blue-500/30 mb-6" style="animation: shindan-float 3s ease-in-out infinite;">
                        <span class="text-5xl">🏍️</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-tighter mb-3">
                        バイク相性診断
                    </h1>
                    <p class="text-gray-400 font-bold text-sm mb-1">5つの質問であなたの最適なバイクを見つけます</p>
                    <p class="text-gray-600 text-[10px] font-bold uppercase tracking-widest">Powered by MotoHub Database</p>
                </div>

                {{-- スタートボタン --}}
                <button onclick="Shindan.start()" class="group relative w-full max-w-xs mx-auto block">
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl blur opacity-40 group-hover:opacity-70 transition duration-500"></div>
                    <div class="relative bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-black text-lg px-10 py-4 rounded-2xl flex items-center justify-center gap-3 active:scale-95 transition-transform">
                        診断スタート
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </div>
                </button>

                <div class="mt-8 flex items-center justify-center gap-6 text-[10px] text-gray-600 font-bold">
                    <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> 約30秒</span>
                    <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4-4v2"/><circle cx="9" cy="7" r="4"/></svg> 登録不要</span>
                    <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 無料</span>
                </div>
            </div>
        </section>

        {{-- ===================== 質問画面 ===================== --}}
        <section id="screen-questions" class="shindan-screen hidden min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4 py-12">
            <div class="w-full max-w-lg">

                {{-- プログレスバー --}}
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-3">
                        <span id="q-counter" class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Question 1 / 5</span>
                        <span id="q-percent" class="text-[10px] font-black text-blue-400 tabular-nums">0%</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden">
                        <div id="q-progress" class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-700 ease-out" style="width: 0%"></div>
                    </div>
                </div>

                {{-- ===== Q1: 排気量 ===== --}}
                <div id="q1" class="question-slide">
                    <div class="mb-8">
                        <span class="inline-block text-[10px] font-black text-blue-400 bg-blue-500/10 px-3 py-1 rounded-full mb-3 uppercase tracking-widest">Q1 排気量</span>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">理想のエンジンサイズは？</h2>
                        <p class="text-sm text-gray-500 font-bold mt-2">スライダーをドラッグして選んでください</p>
                    </div>

                    <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                        <div class="text-center mb-8">
                            <span id="displacement-value" class="text-5xl font-black text-white tabular-nums tracking-tighter">400</span>
                            <span class="text-xl text-gray-500 font-black ml-1">cc</span>
                            <div id="displacement-label" class="text-xs font-bold text-gray-500 mt-2">普通二輪</div>
                        </div>
                        <input type="range" id="slider-displacement" min="50" max="1300" value="400" step="50"
                               class="shindan-slider w-full" oninput="Shindan.updateDisplacement(this.value)">
                        <div class="flex justify-between text-[9px] text-gray-700 font-bold mt-3 px-1">
                            <span>50cc</span><span>125</span><span>250</span><span>400</span><span>650</span><span>1000</span><span>1300+</span>
                        </div>
                    </div>

                    {{-- Q1: 次へのみ（戻るなし） --}}
                    <button onclick="Shindan.next(2)" class="shindan-next-btn mt-6">
                        次の質問へ
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5-5 5"/></svg>
                    </button>
                </div>

                {{-- ===== Q2: 用途 ===== --}}
                <div id="q2" class="question-slide hidden">
                    <div class="mb-8">
                        <span class="inline-block text-[10px] font-black text-green-400 bg-green-500/10 px-3 py-1 rounded-full mb-3 uppercase tracking-widest">Q2 用途</span>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">メインの使い方は？</h2>
                        <p class="text-sm text-gray-500 font-bold mt-2">一番近いものを選んでください</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3" id="usage-options">
                        <button class="shindan-card" data-value="touring" onclick="Shindan.select('usage', 'touring', 3)">
                            <span class="text-3xl mb-2 block">🗾</span>
                            <span class="font-black text-sm block">ツーリング</span>
                            <span class="text-[10px] text-gray-500 block mt-1">遠くへ走りに行きたい</span>
                        </button>
                        <button class="shindan-card" data-value="commute" onclick="Shindan.select('usage', 'commute', 3)">
                            <span class="text-3xl mb-2 block">🏢</span>
                            <span class="font-black text-sm block">通勤・通学</span>
                            <span class="text-[10px] text-gray-500 block mt-1">毎日の移動手段に</span>
                        </button>
                        <button class="shindan-card" data-value="leisure" onclick="Shindan.select('usage', 'leisure', 3)">
                            <span class="text-3xl mb-2 block">☕</span>
                            <span class="font-black text-sm block">街乗り・趣味</span>
                            <span class="text-[10px] text-gray-500 block mt-1">気ままに楽しみたい</span>
                        </button>
                        <button class="shindan-card" data-value="circuit" onclick="Shindan.select('usage', 'circuit', 3)">
                            <span class="text-3xl mb-2 block">🏁</span>
                            <span class="font-black text-sm block">スポーツ走行</span>
                            <span class="text-[10px] text-gray-500 block mt-1">走りを極めたい</span>
                        </button>
                    </div>

                    {{-- Q2: 戻るのみ --}}
                    <button onclick="Shindan.prev(1)" class="shindan-prev-btn mt-6">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 17l-5-5 5-5"/></svg>
                        前の質問へ
                    </button>
                </div>

                {{-- ===== Q3: 予算 ===== --}}
                <div id="q3" class="question-slide hidden">
                    <div class="mb-8">
                        <span class="inline-block text-[10px] font-black text-yellow-400 bg-yellow-500/10 px-3 py-1 rounded-full mb-3 uppercase tracking-widest">Q3 予算</span>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">予算はどれくらい？</h2>
                        <p class="text-sm text-gray-500 font-bold mt-2">乗り出し総額の上限を教えてください</p>
                    </div>

                    <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800">
                        <div class="text-center mb-8">
                            <span class="text-xl text-gray-500 font-black mr-1">¥</span>
                            <span id="budget-value" class="text-5xl font-black text-white tabular-nums tracking-tighter">80</span>
                            <span class="text-xl text-gray-500 font-black ml-1">万円</span>
                        </div>
                        <input type="range" id="slider-budget" min="10" max="400" value="80" step="5"
                               class="shindan-slider w-full" oninput="document.getElementById('budget-value').textContent = this.value">
                        <div class="flex justify-between text-[9px] text-gray-700 font-bold mt-3 px-1">
                            <span>10万</span><span>50万</span><span>100万</span><span>200万</span><span>400万</span>
                        </div>
                    </div>

                    {{-- Q3: 戻る + 次へ --}}
                    <div class="flex gap-3 mt-6">
                        <button onclick="Shindan.prev(2)" class="shindan-prev-btn flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 17l-5-5 5-5"/></svg>
                            前の質問へ
                        </button>
                        <button onclick="Shindan.next(4)" class="shindan-next-btn flex-1">
                            次の質問へ
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5-5 5"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ===== Q4: スタイル ===== --}}
                <div id="q4" class="question-slide hidden">
                    <div class="mb-8">
                        <span class="inline-block text-[10px] font-black text-pink-400 bg-pink-500/10 px-3 py-1 rounded-full mb-3 uppercase tracking-widest">Q4 スタイル</span>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">好みのスタイルは？</h2>
                        <p class="text-sm text-gray-500 font-bold mt-2">直感でピンとくるものを</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3" id="style-options">
                        <button class="shindan-card" data-value="sporty" onclick="Shindan.select('style', 'sporty', 5)">
                            <span class="text-3xl mb-2 block">🔥</span>
                            <span class="font-black text-sm block">スポーティ</span>
                            <span class="text-[10px] text-gray-500 block mt-1">速さ・攻撃的フォルム</span>
                        </button>
                        <button class="shindan-card" data-value="classic" onclick="Shindan.select('style', 'classic', 5)">
                            <span class="text-3xl mb-2 block">⏳</span>
                            <span class="font-black text-sm block">クラシック</span>
                            <span class="text-[10px] text-gray-500 block mt-1">伝統・レトロな美しさ</span>
                        </button>
                        <button class="shindan-card" data-value="wild" onclick="Shindan.select('style', 'wild', 5)">
                            <span class="text-3xl mb-2 block">🦅</span>
                            <span class="font-black text-sm block">ワイルド</span>
                            <span class="text-[10px] text-gray-500 block mt-1">圧倒的な迫力・存在感</span>
                        </button>
                        <button class="shindan-card" data-value="stylish" onclick="Shindan.select('style', 'stylish', 5)">
                            <span class="text-3xl mb-2 block">✨</span>
                            <span class="font-black text-sm block">スタイリッシュ</span>
                            <span class="text-[10px] text-gray-500 block mt-1">洗練された都会的デザイン</span>
                        </button>
                    </div>

                    {{-- Q4: 戻るのみ --}}
                    <button onclick="Shindan.prev(3)" class="shindan-prev-btn mt-6">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 17l-5-5 5-5"/></svg>
                        前の質問へ
                    </button>
                </div>

                {{-- ===== Q5: こだわり ===== --}}
                <div id="q5" class="question-slide hidden">
                    <div class="mb-8">
                        <span class="inline-block text-[10px] font-black text-cyan-400 bg-cyan-500/10 px-3 py-1 rounded-full mb-3 uppercase tracking-widest">Q5 こだわり</span>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">一番こだわるポイントは？</h2>
                        <p class="text-sm text-gray-500 font-bold mt-2">最重視するものを1つだけ</p>
                    </div>

                    <div class="space-y-2" id="priority-options">
                        <button class="shindan-list-card" data-value="speed" onclick="Shindan.select('priority', 'speed', 6)">
                            <span class="text-2xl shrink-0">💨</span>
                            <div><div class="font-black text-sm">パワー・加速</div><div class="text-[10px] text-gray-500 mt-0.5">とにかく速く走りたい</div></div>
                        </button>
                        <button class="shindan-list-card" data-value="fuel" onclick="Shindan.select('priority', 'fuel', 6)">
                            <span class="text-2xl shrink-0">⛽</span>
                            <div><div class="font-black text-sm">燃費・維持費</div><div class="text-[10px] text-gray-500 mt-0.5">コスパ重視で長く乗りたい</div></div>
                        </button>
                        <button class="shindan-list-card" data-value="looks" onclick="Shindan.select('priority', 'looks', 6)">
                            <span class="text-2xl shrink-0">👀</span>
                            <div><div class="font-black text-sm">デザイン・見た目</div><div class="text-[10px] text-gray-500 mt-0.5">カッコよさこそ正義</div></div>
                        </button>
                        <button class="shindan-list-card" data-value="cargo" onclick="Shindan.select('priority', 'cargo', 6)">
                            <span class="text-2xl shrink-0">🧳</span>
                            <div><div class="font-black text-sm">積載性・実用性</div><div class="text-[10px] text-gray-500 mt-0.5">荷物を積んで走りたい</div></div>
                        </button>
                        <button class="shindan-list-card" data-value="comfort" onclick="Shindan.select('priority', 'comfort', 6)">
                            <span class="text-2xl shrink-0">🛋️</span>
                            <div><div class="font-black text-sm">乗り心地・足つき</div><div class="text-[10px] text-gray-500 mt-0.5">快適に楽しく走りたい</div></div>
                        </button>
                    </div>

                    {{-- Q5: 戻るのみ --}}
                    <button onclick="Shindan.prev(4)" class="shindan-prev-btn mt-6">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 17l-5-5 5-5"/></svg>
                        前の質問へ
                    </button>
                </div>

            </div>
        </section>

        {{-- ===================== ローディング画面 ===================== --}}
        <section id="screen-loading" class="shindan-screen hidden min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4">
            <div class="text-center">
                <div class="relative w-24 h-24 mx-auto mb-8">
                    <div class="absolute inset-0 border-4 border-gray-800 rounded-full"></div>
                    <div class="absolute inset-0 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <div class="absolute inset-3 border-4 border-indigo-500 border-b-transparent rounded-full animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                </div>
                <h2 class="text-lg font-black mb-3">あなたにピッタリのバイクを分析中…</h2>
                <p class="text-sm text-gray-500 font-bold" id="loading-text">MotoHubデータベースを検索中</p>
            </div>
        </section>

        {{-- ===================== 結果画面 ===================== --}}
        <section id="screen-result" class="shindan-screen hidden min-h-[calc(100vh-64px)] py-10 px-4">
            <div class="max-w-lg mx-auto">

                {{-- ライダータイプカード --}}
                <div class="rounded-3xl overflow-hidden shadow-2xl mb-8 border border-white/10">
                    <div id="result-type-bg" class="bg-gradient-to-br from-blue-500 to-indigo-600 p-8 sm:p-10 text-center relative overflow-hidden">
                        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 40L40 0H20L0 20M40 40V20L20 40&quot; fill=&quot;%23fff&quot; fill-opacity=&quot;.15&quot;/%3E%3C/svg%3E');"></div>
                        <div class="relative z-10">
                            <div class="text-[10px] font-black text-white/50 uppercase tracking-[0.2em] mb-4">Your Rider Type</div>
                            <div id="result-type-emoji" class="text-6xl mb-4">🏍️</div>
                            <h2 id="result-type-name" class="text-2xl sm:text-3xl font-black text-white mb-4 tracking-tight">ライダータイプ</h2>
                            <p id="result-type-desc" class="text-sm text-white/70 font-bold leading-relaxed max-w-sm mx-auto">説明文</p>
                        </div>
                    </div>
                </div>

                {{-- おすすめバイク --}}
                <div class="mb-8">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="text-yellow-400">🏆</span> Best Match Bikes
                    </h3>
                    <div id="result-bikes" class="space-y-3"></div>
                </div>

                {{-- Xシェア --}}
                <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800 text-center mb-6">
                    <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Share Your Result</p>
                    <button id="share-x-btn" class="bg-white text-black hover:bg-gray-200 font-black py-3 px-8 rounded-xl transition active:scale-95 inline-flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        結果をXでシェア
                    </button>
                </div>

                {{-- MotoHub誘導 --}}
                <a href="{{ route('bikes.search') }}" id="result-search-link" class="block w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-center py-4 rounded-2xl shadow-lg shadow-blue-500/20 transition active:scale-95 mb-4">
                    MotoHubで在庫を探す →
                </a>

                {{-- もう一度 --}}
                <button onclick="Shindan.restart()" class="block w-full text-center text-sm font-bold text-gray-600 hover:text-white py-3 transition">
                    もう一度診断する
                </button>
            </div>
        </section>
    </div>

    <x-slot:scripts>
    <script>
    const Shindan = (() => {
        let answers = {};
        let currentQ = 0;

        function start() {
            _switchScreen('screen-questions');
            _showQuestion(1);
        }

        function restart() {
            answers = {};
            currentQ = 0;
            // 選択状態をリセット
            document.querySelectorAll('.shindan-card, .shindan-list-card').forEach(el => {
                el.classList.remove('shindan-selected');
            });
            // スライダーを初期値に戻す
            document.getElementById('slider-displacement').value = 400;
            updateDisplacement(400);
            document.getElementById('slider-budget').value = 80;
            document.getElementById('budget-value').textContent = '80';
            _switchScreen('screen-start');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- 質問表示 ---
        function _showQuestion(num) {
            currentQ = num;
            document.querySelectorAll('.question-slide').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById('q' + num);
            if (target) {
                target.classList.remove('hidden');
                target.style.animation = 'shindan-fadeSlide 0.4s ease-out';
            }
            // スライダー値を復元
            if (num === 1 && answers.displacement !== undefined) {
                document.getElementById('slider-displacement').value = answers.displacement;
                updateDisplacement(answers.displacement);
            }
            if (num === 3 && answers.budget !== undefined) {
                document.getElementById('slider-budget').value = answers.budget;
                document.getElementById('budget-value').textContent = answers.budget;
            }
            _updateProgress(num);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // 【改善2】進捗バー修正: (num-1)/5 で Q1=0%, Q2=20%, ..., Q5=80%
        function _updateProgress(num) {
            const pct = Math.round(((num - 1) / 5) * 100);
            document.getElementById('q-counter').textContent = `Question ${num} / 5`;
            document.getElementById('q-percent').textContent = `${pct}%`;
            document.getElementById('q-progress').style.width = pct + '%';
        }

        // --- 次へ（スライダー系） ---
        function next(nextNum) {
            if (currentQ === 1) answers.displacement = parseInt(document.getElementById('slider-displacement').value);
            if (currentQ === 3) answers.budget = parseInt(document.getElementById('slider-budget').value);
            _showQuestion(nextNum);
        }

        // 【改善1】前へ戻る
        function prev(prevNum) {
            // 現在のスライダー値を保存してから戻る
            if (currentQ === 1) answers.displacement = parseInt(document.getElementById('slider-displacement').value);
            if (currentQ === 3) answers.budget = parseInt(document.getElementById('slider-budget').value);
            _showQuestion(prevNum);
        }

        // --- 選択肢（カード系） ---
        function select(type, value, nextNum) {
            answers[type] = value;

            // 選択ビジュアル
            const container = document.getElementById(type + '-options');
            if (container) {
                container.querySelectorAll('.shindan-card, .shindan-list-card').forEach(b => b.classList.remove('shindan-selected'));
                const sel = container.querySelector(`[data-value="${value}"]`);
                if (sel) sel.classList.add('shindan-selected');
            }

            if (nextNum > 5) {
                setTimeout(() => _diagnose(), 500);
            } else {
                setTimeout(() => _showQuestion(nextNum), 400);
            }
        }

        // --- 排気量ラベル更新 ---
        function updateDisplacement(val) {
            document.getElementById('displacement-value').textContent = val;
            const v = parseInt(val);
            let label = '大型二輪';
            if (v <= 50) label = '原付（免許不要）';
            else if (v <= 125) label = '小型二輪（AT小型限定可）';
            else if (v <= 400) label = '普通二輪';
            document.getElementById('displacement-label').textContent = label;
        }

        // --- 診断実行 ---
        async function _diagnose() {
            _switchScreen('screen-loading');

            const texts = ['MotoHubデータベースを検索中…', '相性スコアを計算中…', 'ベストマッチを選定中…'];
            let i = 0;
            const iv = setInterval(() => {
                i = (i + 1) % texts.length;
                document.getElementById('loading-text').textContent = texts[i];
            }, 900);

            try {
                const res = await fetch('/shindan/diagnose', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(answers),
                });
                const data = await res.json();
                clearInterval(iv);
                await new Promise(r => setTimeout(r, 1500));
                _showResult(data);
            } catch (err) {
                clearInterval(iv);
                document.getElementById('loading-text').textContent = 'エラーが発生しました。もう一度お試しください。';
                console.error(err);
            }
        }

        // --- 結果表示 ---
        function _showResult(data) {
            _switchScreen('screen-result');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const rt = data.rider_type;
            document.getElementById('result-type-emoji').textContent = rt.emoji;
            document.getElementById('result-type-name').textContent = rt.name;
            document.getElementById('result-type-desc').textContent = rt.desc;
            document.getElementById('result-type-bg').className = `bg-gradient-to-br ${rt.bg} p-8 sm:p-10 text-center relative overflow-hidden`;

            // バイクカード
            const container = document.getElementById('result-bikes');
            container.innerHTML = '';

            if (!data.bikes || !data.bikes.length) {
                container.innerHTML = '<div class="bg-gray-900 rounded-2xl p-6 border border-gray-800 text-center"><p class="text-sm text-gray-500 font-bold">条件に合うバイクが見つかりませんでした</p><a href="/bikes/search" class="inline-block mt-3 text-sm font-bold text-blue-400 hover:underline">MotoHubで検索 →</a></div>';
                return;
            }

            const medals = ['🥇', '🥈', '🥉'];
            data.bikes.forEach((bike, idx) => {
                const card = document.createElement('a');
                card.href = `/bikes/search?bike_model_id=${bike.id}`;
                card.className = 'group flex items-stretch bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden hover:border-gray-600 transition-all duration-300';
                card.style.animation = `shindan-fadeSlide 0.5s ease-out ${idx * 0.12}s both`;
                card.innerHTML = `
                    <div class="w-28 sm:w-32 shrink-0 bg-gray-800 relative overflow-hidden">
                        ${bike.image_url
                            ? `<img src="${bike.image_url}" alt="${bike.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">`
                            : '<div class="w-full h-full flex items-center justify-center text-gray-700"><svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></div>'}
                        <div class="absolute top-2 left-2 text-lg">${medals[idx] || ''}</div>
                    </div>
                    <div class="flex-1 p-4 flex flex-col justify-center">
                        <div class="text-[9px] font-bold text-gray-600 mb-0.5">${bike.manufacturer || ''}</div>
                        <h4 class="text-sm font-black text-white mb-2 leading-tight group-hover:text-blue-400 transition-colors">${bike.name}</h4>
                        <div class="flex flex-wrap gap-1.5">
                            ${bike.displacement ? `<span class="text-[9px] font-bold bg-gray-800 text-gray-400 px-2 py-0.5 rounded">${bike.displacement}cc</span>` : ''}
                            ${bike.avg_price ? `<span class="text-[9px] font-bold bg-blue-500/10 text-blue-400 px-2 py-0.5 rounded">平均${bike.avg_price}万</span>` : ''}
                            <span class="text-[9px] font-bold bg-green-500/10 text-green-400 px-2 py-0.5 rounded">${bike.listings_count}台</span>
                        </div>
                    </div>
                    <div class="flex items-center pr-4">
                        <svg class="w-4 h-4 text-gray-700 group-hover:text-blue-400 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </div>
                `;
                container.appendChild(card);
            });

            // ★修正: XシェアボタンのURL生成（bikes=xx,yy,zz の形式に変更）
            const topBike = data.bikes[0];
            const shareText = `🏍️ バイク相性診断やってみた！\n\n${rt.emoji} ライダータイプ「${rt.name}」\n🏆 おすすめ: ${topBike?.name || '??'}\n\nあなたも診断してみよう👇`;

            const bikeIds = data.bikes.map(b => b.id).join(',');
            const shareUrl = `${location.origin}/shindan/result?type=${rt.key}&bikes=${bikeIds}`;

            document.getElementById('share-x-btn').onclick = () => {
                window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`, '_blank', 'width=550,height=420');
            };

            if (topBike) {
                document.getElementById('result-search-link').href = `/bikes/search?bike_model_id=${topBike.id}`;
            }
        }

        // --- 画面切替ユーティリティ ---
        function _switchScreen(id) {
            document.querySelectorAll('.shindan-screen').forEach(el => el.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
        }

        return { start, restart, next, prev, select, updateDisplacement };
    })();
    </script>
    </x-slot:scripts>

    <style>
        /* ========== カスタムスライダー ========== */
        .shindan-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 8px;
            background: #1f2937;
            border-radius: 999px;
            outline: none;
        }
        .shindan-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.4), 0 0 0 4px rgba(59, 130, 246, 0.3);
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .shindan-slider::-webkit-slider-thumb:hover {
            box-shadow: 0 2px 16px rgba(0,0,0,0.5), 0 0 0 6px rgba(59, 130, 246, 0.4);
        }
        .shindan-slider::-moz-range-thumb {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.4), 0 0 0 4px rgba(59, 130, 246, 0.3);
            cursor: pointer;
            border: none;
        }

        /* ========== 選択カード（グリッド） ========== */
        .shindan-card {
            background: rgba(17, 24, 39, 0.8);
            border: 2px solid #1f2937;
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .shindan-card:hover { border-color: #374151; background: rgba(31, 41, 55, 0.8); }
        .shindan-card:active { transform: scale(0.97); }
        .shindan-card.shindan-selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
        }

        /* ========== 選択カード（リスト） ========== */
        .shindan-list-card {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(17, 24, 39, 0.8);
            border: 2px solid #1f2937;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            text-align: left;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .shindan-list-card:hover { border-color: #374151; background: rgba(31, 41, 55, 0.8); }
        .shindan-list-card:active { transform: scale(0.98); }
        .shindan-list-card.shindan-selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
        }

        /* ========== 次へボタン ========== */
        .shindan-next-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: 900;
            padding: 1rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        .shindan-next-btn:hover { background: rgba(255, 255, 255, 0.1); }
        .shindan-next-btn:active { transform: scale(0.98); }

        /* ========== 前へボタン ========== */
        .shindan-prev-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: #6b7280;
            font-weight: 700;
            font-size: 0.875rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        .shindan-prev-btn:hover { color: #d1d5db; border-color: rgba(255, 255, 255, 0.15); }
        .shindan-prev-btn:active { transform: scale(0.98); }

        /* ========== アニメーション ========== */
        @keyframes shindan-fadeSlide {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes shindan-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
    </style>
</x-layout>
