<x-layout>
    <x-slot:title>バイク特集一覧 | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubの特集ページ一覧。通勤向けバイク、格安バイク、大型バイクなど、目的やテーマ別にバイクを探せます。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- ヘッダー --}}
        <div class="bg-gray-900 pt-8 pb-12 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-white transition-colors">HOME</a></li>
                        <li><span class="text-gray-600">＞</span></li>
                        <li><span class="text-white">特集一覧</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">バイク特集一覧</h1>
                <p class="mt-2 text-sm text-gray-400 font-bold">目的やテーマ別にバイクを探せる特集ページです。気になるテーマをタップして、ぴったりのバイクを見つけましょう。</p>
            </div>
        </div>

        {{-- 特集カードグリッド --}}
        <div class="max-w-7xl mx-auto px-4 py-10 -mt-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($features as $feature)
                    <a href="{{ $feature->url }}"
                       class="group relative overflow-hidden rounded-2xl bg-gray-800 {{ $feature->color ?? '' }} shadow-md hover:shadow-xl transition-all duration-300 border border-white/10">
                        <div class="p-6 h-44 flex flex-col justify-between relative z-10">
                            {{-- アイコン --}}
                            @if($feature->icon)
                            <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mb-auto">
                                <i data-lucide="{{ $feature->icon }}" class="w-5 h-5 text-white"></i>
                            </div>
                            @endif

                            {{-- タイトル --}}
                            <h2 class="text-base sm:text-lg font-black text-white leading-snug drop-shadow-sm group-hover:-translate-y-0.5 transition-transform duration-300">
                                {{ $feature->title }}
                            </h2>

                            {{-- 矢印 --}}
                            <div class="absolute bottom-5 right-5 w-8 h-8 bg-white/20 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i data-lucide="arrow-right" class="w-4 h-4 text-white"></i>
                            </div>
                        </div>

                        {{-- 背景の装飾アイコン --}}
                        @if($feature->icon)
                        <div class="absolute -right-6 -bottom-6 opacity-10 transform group-hover:scale-110 group-hover:-rotate-12 transition-transform duration-500 pointer-events-none">
                            <i data-lucide="{{ $feature->icon }}" class="w-32 h-32 text-white"></i>
                        </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 検索への導線 --}}
        <div class="max-w-7xl mx-auto px-4 pb-16">
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center shadow-sm">
                <h3 class="text-lg font-black text-gray-900 mb-2">お探しの条件が見つからない場合</h3>
                <p class="text-sm text-gray-500 font-bold mb-5">詳細検索でお好みの条件を自由に組み合わせて検索できます。</p>
                <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-gray-800 transition-colors shadow-md">
                    <i data-lucide="search" class="w-4 h-4"></i> 詳細検索ページへ
                </a>
            </div>
        </div>
    </div>
</x-layout>
