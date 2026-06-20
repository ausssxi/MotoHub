<x-layout>
    {{-- ★公開表示は公開ハンドルのみ。user->name(本名)・メール・内部IDは一切出さない。 --}}
    <x-slot:title>{{ $handle }}のガレージ | ライダープロフィール | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $handle }}さんの公開ガレージ（{{ $garages->count() }}台）。愛車・燃費記録・整備ログを公開中。</x-slot:metaDescription>
    {{-- 暫定 noindex（本格版＝フォロー等が固まるまで半端な公開面を index させない） --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- プロフィールヘッダー（公開ハンドルのみ） --}}
        <div class="bg-gradient-to-r from-pink-600 to-rose-500 text-white py-12 sm:py-16">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-3 backdrop-blur-sm">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-1">Rider Profile</p>
                <h1 class="text-2xl sm:text-3xl font-black mb-2">{{ $handle }}</h1>
                <p class="text-xs sm:text-sm text-white/80 font-medium">公開ガレージ {{ $garages->count() }} 台</p>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-10">
            {{-- パンくず --}}
            <nav class="mb-6 text-xs font-bold text-gray-400 flex items-center gap-1 flex-wrap">
                <a href="{{ route('bikes.index') }}" class="hover:text-gray-600 transition-colors">トップ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="{{ route('garage.public.index') }}" class="hover:text-gray-600 transition-colors">みんなのガレージ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-gray-600">{{ $handle }}</span>
            </nav>

            <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                <i data-lucide="warehouse" class="w-5 h-5 text-pink-600"></i> {{ $handle }} のガレージ
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($garages as $g)
                    <a href="{{ route('garage.public.show', $g['id']) }}" class="group block bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:border-pink-200 transition-all duration-300">
                        <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
                            @if($g['image'])
                                <img src="{{ $g['image'] }}" alt="{{ $g['bike_name'] }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                     loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    <i data-lucide="bike" class="w-8 h-8"></i>
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <p class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $g['manufacturer'] }}</p>
                            <h3 class="text-sm font-black text-gray-800 group-hover:text-pink-600 transition-colors line-clamp-1 mb-1">{{ $g['bike_name'] }}</h3>
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] font-bold text-gray-400">{{ number_format($g['odometer']) }} km</p>
                                @if($g['model_year'])
                                    <span class="text-[10px] text-gray-400">{{ $g['model_year'] }}年式</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
    </x-slot:scripts>
</x-layout>
