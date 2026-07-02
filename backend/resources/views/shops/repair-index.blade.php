<x-layout>
    <x-slot:title>バイクの整備・修理ショップを探す｜認証工場・車検 - MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ number_format($totalCount) }}店のバイク整備・修理店・認証工場を都道府県別に一覧表示。点検・整備・修理・車検に対応するお店を探せます。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.breadcrumb-shop-repair-area />
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">バイク整備・修理店</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3">
                    バイクの整備・修理ショップを探す
                </h1>
                <p class="text-sm text-gray-500">
                    全国<span class="font-bold text-green-600">{{ number_format($totalCount) }}</span>店のバイク整備・修理店・認証工場を都道府県別に掲載しています。点検・整備・修理・車検に対応するお店を探せます。
                </p>
            </div>

            {{-- 地方ブロック別 --}}
            @foreach($regions as $regionName => $prefs)
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    {{ $regionName }}
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($prefs as $pref => $coords)
                    @php $count = $prefCounts[$pref] ?? 0; @endphp
                    <a href="{{ $count > 0 ? route('shops.repair.prefecture', $pref) : '#' }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block {{ $count === 0 ? 'opacity-50 pointer-events-none' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-black text-gray-800">{{ $pref }}</span>
                            <span class="text-xs font-bold {{ $count > 0 ? 'text-green-600' : 'text-gray-300' }}">
                                {{ number_format($count) }}店
                            </span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endforeach

            {{-- 未掲載店の投稿導線 --}}
            <a href="{{ route('shops.submit.create') }}"
               class="mt-8 flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <i data-lucide="plus-circle" class="w-6 h-6 text-emerald-600"></i>
                    <div>
                        <p class="text-sm font-black text-gray-800">載っていない整備・修理店を教える</p>
                        <p class="text-xs text-gray-500 font-bold">掲載リクエストを送る（承認後に掲載されます）</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-emerald-400"></i>
            </a>

            {{-- 販売店一覧へのリンク --}}
            <div class="text-center mt-8">
                <a href="{{ route('shops.area.index') }}"
                   class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-green-300 text-gray-700 hover:text-green-600 font-bold text-sm py-3 px-8 rounded-xl transition-colors shadow-sm">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    販売店（バイクショップ）一覧を見る
                </a>
            </div>
        </div>
    </div>
</x-layout>
