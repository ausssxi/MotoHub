<x-layout>
    <x-slot:title>新規会員登録 - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="relative min-h-[calc(100vh-4rem)] flex flex-col justify-center items-center py-12 sm:px-6 lg:px-8 bg-gray-900 overflow-hidden">
        
        {{-- 背景画像 --}}
        <div class="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c3d?q=80&w=2070&auto=format&fit=crop" 
                 class="w-full h-full object-cover opacity-40 blur-sm" 
                 alt="Background">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
        </div>

        <div class="relative z-10 w-full sm:max-w-md">
            {{-- タイトルエリア --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center bg-blue-600 text-white p-3 rounded-2xl shadow-xl shadow-blue-500/20 mb-4">
                    <i data-lucide="user-plus" class="w-8 h-8"></i>
                </div>
                <h2 class="text-2xl font-black text-white tracking-tight">アカウント作成</h2>
                <p class="text-sm text-gray-300 font-bold mt-2">無料で便利な機能を使おう</p>
            </div>

            {{-- カード --}}
            <div class="bg-white/95 backdrop-blur-md px-8 py-10 shadow-2xl shadow-black/50 sm:rounded-3xl border border-white/20">
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-xs font-bold text-gray-500 mb-1 ml-1">ニックネーム</label>
                        <input id="name" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3" 
                               type="text" name="name" :value="old('name')" required autofocus autocomplete="name" 
                               placeholder="モトハブ太郎" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div class="mt-4">
                        <label for="email" class="block text-xs font-bold text-gray-500 mb-1 ml-1">メールアドレス</label>
                        <input id="email" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3" 
                               type="email" name="email" :value="old('email')" required autocomplete="username" 
                               placeholder="your@email.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <label for="password" class="block text-xs font-bold text-gray-500 mb-1 ml-1">パスワード</label>
                        <input id="password" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3"
                               type="password" name="password" required autocomplete="new-password" 
                               placeholder="8文字以上" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mt-4">
                        <label for="password_confirmation" class="block text-xs font-bold text-gray-500 mb-1 ml-1">パスワード (確認)</label>
                        <input id="password_confirmation" class="block mt-1 w-full bg-gray-50 border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500 font-bold p-3"
                               type="password" name="password_confirmation" required autocomplete="new-password" 
                               placeholder="もう一度入力" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="flex flex-col items-center justify-end mt-8 gap-4">
                        <button class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transform active:scale-95 transition flex items-center justify-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            登録する
                        </button>

                        <div class="w-full border-t border-gray-100 mt-2 pt-6 text-center">
                            <p class="text-xs text-gray-400 font-bold mb-3">すでにアカウントをお持ちですか？</p>
                            <a href="{{ route('login') }}" class="block w-full border-2 border-gray-100 hover:border-black hover:text-black text-gray-600 font-bold py-2.5 rounded-xl transition text-center text-sm">
                                ログイン画面へ
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layout>