<x-layout>
    {{-- 
        Breezeのデフォルト画面（パスワードリセット等）が
        <x-guest-layout> を使っている場合でも、
        強制的に共通レイアウト(x-layout)の中で表示されるようにします。
    --}}
    
    <x-slot:title>
        {{ config('app.name', 'MotoHub') }}
    </x-slot:title>

    <x-slot:navigation>
        {{-- 認証系ページでは検索バーは非表示 --}}
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    {{-- 背景付きのメインエリア --}}
    <div class="relative min-h-[calc(100vh-4rem)] flex flex-col justify-center items-center py-12 sm:px-6 lg:px-8 bg-gray-900 overflow-hidden">
        
        {{-- 背景画像 --}}
        <div class="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1558981806-ec527fa84c3d?q=80&w=2070&auto=format&fit=crop" 
                 class="w-full h-full object-cover opacity-40 blur-sm" 
                 alt="Background">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
        </div>

        {{-- コンテンツ --}}
        <div class="relative z-10 w-full sm:max-w-md bg-white/95 backdrop-blur-md px-8 py-10 shadow-2xl shadow-black/50 sm:rounded-3xl border border-white/20">
            {{ $slot }}
        </div>
    </div>
</x-layout>