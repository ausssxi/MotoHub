<x-layout>
    <x-slot:title>{{ $manufacturer->name }}の中古バイク一覧【{{ number_format($totalCount) }}台】相場・人気モデル｜MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $manufacturer->name }}の中古バイク{{ number_format($totalCount) }}台を毎日更新。@if($minMan && $maxMan)相場{{ $minMan }}〜{{ $maxMan }}万円、@endif人気モデルから相場と在庫を比較できます。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.manufacturer_hub', ['makerSlug' => $manufacturer->slug]) }}</x-slot:canonical>

    @if($totalCount < 10)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        // BreadcrumbList（HOME > 車種一覧 > メーカー）
        $makerBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'トップ', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => '車種一覧', 'item' => route('bikes.models')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $manufacturer->name, 'item' => route('bikes.manufacturer_hub', ['makerSlug' => $manufacturer->slug])],
            ],
        ];
        // ItemList（人気モデル＝スポークの車種ページへ）
        $makerItemList = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $manufacturer->name.'の人気中古バイク車種',
            'itemListElement' => $models->values()->map(fn ($m, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $m->name,
                'url' => url($m->seo_url),
            ])->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($makerBreadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if($models->isNotEmpty())
    <script type="application/ld+json">{!! json_encode($makerItemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-5xl mx-auto px-4">

            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-3 tracking-tighter">
                    {{ $manufacturer->name }}の中古バイク
                </h1>
                <p class="text-gray-400 text-sm font-bold">
                    {{ number_format($totalCount) }}台掲載中@if($minMan && $maxMan)・相場 {{ $minMan }}〜{{ $maxMan }}万円@endif・最終更新 {{ now()->format('Y年n月j日') }}
                </p>
            </div>

            {{-- 人気モデルから探す（ハブ→スポーク：車種ページへの内部リンク） --}}
            @if($models->isNotEmpty())
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>
                    {{ $manufacturer->name }}の人気モデルから探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach($models as $m)
                        <a href="{{ $m->seo_url }}" class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                            <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                                @if($m->image_url)
                                    <img src="{{ $m->image_url }}" alt="{{ $m->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i data-lucide="bike" class="w-6 h-6"></i>
                                    </div>
                                @endif
                            </div>
                            <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">{{ $m->name }}</h3>
                            <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ number_format($m->listings_count) }}台</span>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 全件検索リンク --}}
            <div class="text-center mt-10">
                <a href="{{ route('bikes.search', ['manufacturer_id' => $manufacturer->id]) }}"
                   class="inline-flex items-center gap-2 px-8 py-3 bg-black text-white text-sm font-black rounded-full hover:bg-gray-800 transition-colors">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    {{ $manufacturer->name }}のバイクをすべて見る
                </a>
            </div>

            {{-- 戻るリンク --}}
            <div class="mt-20 pt-10 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.models') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> すべての車種を見る
                </a>
            </div>

        </div>
    </div>
</x-layout>
