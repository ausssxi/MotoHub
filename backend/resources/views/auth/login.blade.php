<x-layout>
    <x-slot:title>ログイン - MotoHub</x-slot:title>

    {{-- ログイン画面なので検索バーは非表示に --}}
    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    {{-- メインエリア（全画面背景付き） --}}
    <div class="relative min-h-[calc(100vh-4rem)] flex flex-col justify-center items-center py-12 sm:px-6 lg:px-8 bg-gray-900 overflow-hidden">
        
        {{-- 背景画像 --}}
        <div class="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c3d?q=80&w=2070&auto=format&fit=crop" 
                 class="w-full h-full object-cover opacity-40 blur-sm" 
                 alt="Background">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
        </div>

        {{-- フォームコンテナ --}}
        <div class="relative z-10 w-full sm:max-w-md">
            {{-- タイトルエリア --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center bg-white text-black p-3 rounded-2xl shadow-xl shadow-blue-500/20 mb-4">
                    <i data-lucide="bike" class="w-8 h-8"></i>
                </div>
                <h2 class="text-2xl font-black text-white tracking-tight">おかえりなさい！</h2>
                <p class="text-sm text-gray-300 font-bold mt-2">ログインしてバイク探しを続けましょう</p>
            </div>

            {{-- カード --}}
            <div class="bg-white/95 backdrop-blur-md px-8 py-10 shadow-2xl shadow-black/50 sm:rounded-3xl border border-white/20">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-500 mb-1 ml-1">メールアドレス</label>
                        <input id="email" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3" 
                               type="email" name="email" :value="old('email')" required autofocus autocomplete="username" 
                               placeholder="your@email.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-5">
                        <label for="password" class="block text-xs font-bold text-gray-500 mb-1 ml-1">パスワード</label>
                        <input id="password" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3"
                               type="password" name="password" required autocomplete="current-password" 
                               placeholder="••••••••" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="block mt-4">
                        <label for="remember_me" class="inline-flex items-center cursor-pointer">
                            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                            <span class="ml-2 text-xs font-bold text-gray-600">ログイン状態を保存する</span>
                        </label>
                    </div>

                    <div class="flex flex-col items-center justify-end mt-8 gap-4">
                        <button class="w-full bg-black hover:bg-gray-800 text-white font-black py-3.5 rounded-xl shadow-lg transform active:scale-95 transition flex items-center justify-center gap-2">
                            <i data-lucide="log-in" class="w-4 h-4"></i>
                            ログイン
                        </button>

                        @if (Route::has('password.request'))
                            <a class="text-xs text-gray-400 hover:text-blue-600 font-bold transition-colors" href="{{ route('password.request') }}">
                                パスワードを忘れた場合
                            </a>
                        @endif
                        
                        <div class="w-full border-t border-gray-100 mt-2 pt-6 text-center">
                            <p class="text-xs text-gray-400 font-bold mb-3">アカウントをお持ちでないですか？</p>
                            <a href="{{ route('register') }}" class="block w-full border-2 border-gray-100 hover:border-black hover:text-black text-gray-600 font-bold py-2.5 rounded-xl transition text-center text-sm">
                                新規会員登録 (無料)
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layout>