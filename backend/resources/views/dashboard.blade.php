<x-layout>
    <x-slot:title>マイページ - MotoHub</x-slot:title>

    <x-slot:scripts>
        <script>
            // JSの読み込みと準備(init)が完全に終わるのを待つ
            window.addEventListener('load', () => {
                // さらに、初期化処理が終わる余裕を持たせる
                setTimeout(async () => {
                    if (typeof HistoryManager === 'undefined') {
                        document.getElementById('history-empty').classList.remove('hidden');
                        return;
                    }

                    try {
                        const ids = await HistoryManager.fetchIds();

                        if (ids && ids.length > 0) {
                            document.getElementById('history-section').classList.remove('hidden');
                            document.getElementById('history-empty').classList.add('hidden');
                            await HistoryManager.render('history-container');
                        } else {
                            document.getElementById('history-section').classList.add('hidden');
                            document.getElementById('history-empty').classList.remove('hidden');
                        }
                    } catch (error) {
                        console.error('履歴の読み込みに失敗しました:', error);
                        document.getElementById('history-empty').classList.remove('hidden');
                    }

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }, 100); 
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- ヘッダーエリア --}}
            <div class="mb-10">
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2">
                    <i data-lucide="layout-dashboard" class="w-6 h-6 text-blue-600"></i>
                    マイページ
                </h1>
                <p class="text-xs font-bold text-gray-500 mt-1 ml-8">ようこそ、{{ Auth::user()->name }} さん</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                {{-- 左カラム: メインメニュー --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- 愛車ガレージ --}}
                    <div class="bg-gradient-to-br from-blue-600 to-blue-700 overflow-hidden shadow-lg shadow-blue-500/30 rounded-2xl border border-blue-500 hover:shadow-xl transition group relative">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i data-lucide="wrench" class="w-32 h-32 text-white"></i>
                        </div>
                        <div class="p-6 relative z-10">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-black text-white flex items-center gap-2">
                                    <i data-lucide="bike" class="w-6 h-6"></i>
                                    愛車ガレージ
                                </h3>
                                <span class="bg-white/20 text-white text-[10px] font-bold px-2 py-1 rounded-full">
                                    New Function
                                </span>
                            </div>
                            
                            <p class="text-sm text-blue-100 mb-6 font-bold leading-relaxed">
                                あなたのバイクの燃費記録や整備履歴を管理できます。<br>
                                オイル交換の時期や、カスタムの記録を残しましょう。
                            </p>

                            <a href="{{ route('mybikes.index') ?? '#' }}" class="inline-flex items-center gap-2 bg-white text-blue-700 px-5 py-3 rounded-xl text-sm font-black hover:bg-blue-50 transition-colors shadow-sm">
                                ガレージへ移動する <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                    
                    {{-- お気に入りへのショートカット --}}
                    <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-black text-gray-900 flex items-center gap-2">
                                    <i data-lucide="heart" class="w-5 h-5 text-red-500 fill-current"></i>
                                    お気に入り車両
                                </h3>
                                <a href="{{ route('wishlist') }}" class="text-xs font-bold text-blue-600 hover:underline">すべて見る</a>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-6">
                                気になるバイクを保存して、価格や状態を比較しましょう。
                                {{-- ログイン直後でお気に入りが未実装の場合はエラーを防ぐため0を表示 --}}
                                <br>現在 <span class="font-black text-lg text-black mx-1">{{ method_exists(Auth::user(), 'favorites') ? Auth::user()->favorites()->count() : 0 }}</span> 台登録しています。
                            </p>

                            <a href="{{ route('wishlist') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white px-5 py-3 rounded-xl text-sm font-bold hover:bg-gray-700 transition-colors">
                                お気に入り一覧を開く <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                    {{-- 最近見たバイク（閲覧履歴あり） --}}
                    <div id="history-section" class="hidden bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="p-4 sm:p-6">
                            {{-- ★追加: 見出し --}}
                            <h3 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-4">
                                <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
                                最近見たバイク
                            </h3>
                            
                            {{-- ★修正: 横並び・スクロール用のクラスを追加 --}}
                            <div id="history-container" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                                {{-- HistoryManager.render() がここにカードを描画 --}}
                            </div>
                        </div>
                    </div>

                    {{-- 最近見たバイク（閲覧履歴なし） --}}
                    <div id="history-empty" class="hidden bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 opacity-60">
                        <div class="p-6">
                            <h3 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-2">
                                <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
                                最近見たバイク
                            </h3>
                            <p class="text-sm text-gray-400">まだ閲覧履歴がありません。バイクを見てみましょう！</p>
                            <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 mt-4 text-sm font-bold text-blue-600 hover:underline">
                                バイクを探す <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                </div>

                {{-- 右カラム: アカウント設定など --}}
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100">
                        <div class="p-6">
                            <h3 class="text-sm font-black text-gray-900 mb-4 uppercase tracking-widest">Account</h3>
                            
                            <ul class="space-y-3">
                                <li>
                                    <a href="{{ route('profile.edit') }}" class="flex items-center justify-between p-3 rounded-xl bg-gray-50 hover:bg-blue-50 text-gray-700 hover:text-blue-600 font-bold text-sm transition-colors group">
                                        <span class="flex items-center gap-2"><i data-lucide="user-cog" class="w-4 h-4 text-gray-400 group-hover:text-blue-500"></i> プロフィール編集</span>
                                        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 group-hover:text-blue-400"></i>
                                    </a>
                                </li>
                                {{-- LINE連携 --}}
                                <li>
                                    @if(auth()->user()->hasLineLinked())
                                        {{-- 連携済み --}}
                                        <div class="p-3 rounded-xl bg-green-50 border border-green-100">
                                            <div class="flex items-center justify-between">
                                                <span class="flex items-center gap-2 text-sm font-bold text-green-700">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#06C755"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                                    LINE連携済み
                                                </span>
                                                <form method="POST" action="{{ route('auth.line.unlink') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-[10px] font-bold text-gray-400 hover:text-red-500 transition-colors" onclick="return confirm('LINE連携を解除しますか？')">
                                                        解除
                                                    </button>
                                                </form>
                                            </div>
                                            <p class="text-[10px] text-green-600 font-bold mt-1">値下げ通知をLINEで受け取れます</p>
                                        </div>
                                    @else
                                        {{-- 未連携 --}}
                                        <a href="{{ route('auth.line.redirect') }}" class="flex items-center justify-between p-3 rounded-xl bg-line-green hover:bg-line-green-hover text-white font-bold text-sm transition-colors group shadow-sm">
                                            <span class="flex items-center gap-2">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                                LINE連携する
                                            </span>
                                            <i data-lucide="chevron-right" class="w-4 h-4 opacity-70"></i>
                                        </a>
                                        <p class="text-[10px] text-gray-400 font-bold mt-1.5 ml-1">連携すると値下げ通知をLINEで受信できます</p>
                                    @endif
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full flex items-center justify-between p-3 rounded-xl bg-gray-50 hover:bg-red-50 text-gray-700 hover:text-red-600 font-bold text-sm transition-colors group">
                                            <span class="flex items-center gap-2"><i data-lucide="log-out" class="w-4 h-4 text-gray-400 group-hover:text-red-500"></i> ログアウト</span>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layout>