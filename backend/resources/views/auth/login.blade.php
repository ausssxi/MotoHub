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
                {{-- === ここから追加 === --}}
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-xs">
                            <span class="px-4 bg-white text-gray-400 font-bold">または</span>
                        </div>
                    </div>

                    <a href="{{ route('auth.google.redirect') }}" 
                       class="mt-4 w-full inline-flex items-center justify-center gap-3 bg-white border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-3 px-4 rounded-xl transition-all active:scale-95 shadow-sm">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Googleアカウントでログイン
                    </a>
                </div>
                {{-- === ここまで追加 === --}}
            </div> {{-- カード閉じ --}}
        </div>
    </div>
</x-layout>