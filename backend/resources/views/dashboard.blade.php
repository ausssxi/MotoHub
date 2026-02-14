<x-layout>
    <x-slot:title>マイページ - MotoHub</x-slot:title>

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
                                <br>現在 <span class="font-black text-lg text-black mx-1">{{ Auth::user()->favorites()->count() }}</span> 台登録しています。
                            </p>

                            <a href="{{ route('wishlist') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white px-5 py-3 rounded-xl text-sm font-bold hover:bg-gray-700 transition-colors">
                                お気に入り一覧を開く <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                    {{-- 検索履歴（今はまだないですが将来用） --}}
                    <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 opacity-60">
                        <div class="p-6">
                            <h3 class="text-lg font-black text-gray-900 flex items-center gap-2 mb-2">
                                <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
                                最近見たバイク
                            </h3>
                            <p class="text-xs text-gray-400 font-bold">※この機能は準備中です</p>
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