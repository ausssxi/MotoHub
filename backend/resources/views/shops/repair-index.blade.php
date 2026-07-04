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
                    @if($serviceTag)「{{ $serviceTag }}」対応のバイク整備・修理店@else バイクの整備・修理ショップを探す @endif
                </h1>
                <p class="text-sm text-gray-500">
                    @if($serviceTag)
                        「{{ $serviceTag }}」に対応する整備・修理店がある地域を表示しています。
                        <a href="{{ route('shops.repair.index') }}" class="font-bold text-green-600 hover:underline">すべて表示 →</a>
                    @else
                        全国<span class="font-bold text-green-600">{{ number_format($totalCount) }}</span>店のバイク整備・修理店・認証工場を都道府県別に掲載しています。点検・整備・修理・車検に対応するお店を探せます。
                    @endif
                </p>
            </div>

            {{-- 店名で探す --}}
            <x-shop-name-search type="repair_only" accent="green" />

            {{-- A-2: 目的（サービス）から探す --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="wrench" class="w-4 h-4 text-green-600"></i> 目的から探す
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($serviceOptions as $tag)
                    <a href="{{ route('shops.repair.index', ['service' => $tag]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors {{ $serviceTag === $tag ? 'bg-green-600 text-white border-green-600' : 'bg-gray-50 text-gray-600 border-gray-100 hover:bg-green-50 hover:border-green-200' }}">
                        {{ $tag }}
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- 地方ブロック別（掲載ありの県のみ・県直下に主要市区町村チップ） --}}
            @if($totalCount === 0)
            <p class="text-sm text-gray-400 text-center py-8">
                @if($serviceTag)「{{ $serviceTag }}」対応の店舗はまだ登録されていません。@else 該当する店舗がまだありません。@endif
            </p>
            @endif
            @foreach($regions as $regionName => $prefs)
            @php $regionPrefs = collect($prefs)->keys()->filter(fn ($p) => ($prefCounts[$p] ?? 0) > 0); @endphp
            @if($regionPrefs->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    {{ $regionName }}
                </h2>
                <div class="space-y-3">
                    @foreach($regionPrefs as $pref)
                    @php $count = $prefCounts[$pref] ?? 0; @endphp
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <a href="{{ route('shops.repair.prefecture', $pref) }}"
                           class="flex items-center justify-between group">
                            <span class="text-sm font-black text-gray-800 group-hover:text-green-700">{{ $pref }}</span>
                            <span class="text-xs font-bold text-green-600">{{ number_format($count) }}店 →</span>
                        </a>
                        @if(!empty($topCities[$pref]))
                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            @foreach($topCities[$pref] as $c)
                            <a href="{{ route('shops.repair.city', [$pref, $c['city']]) }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 bg-gray-50 border border-gray-100 rounded-lg px-2.5 py-1 hover:bg-green-50 hover:border-green-200 hover:text-green-700 transition-colors">
                                {{ $c['city'] }} <span class="text-gray-400">{{ $c['count'] }}</span>
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </section>
            @endif
            @endforeach

            {{-- 未掲載店の投稿導線 --}}
            <a href="{{ route('shops.submit.create', ['type' => 'repair_only']) }}"
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

            {{-- A-3: 関連機能への回遊 --}}
            <section class="mt-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="compass" class="w-4 h-4 text-green-600"></i> 関連コンテンツ
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <a href="{{ route('trouble.index') }}" class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-sm transition-all">
                        <i data-lucide="stethoscope" class="w-5 h-5 text-blue-500"></i>
                        <div>
                            <p class="text-xs font-black text-gray-800">バイクの調子が悪い？</p>
                            <p class="text-[10px] text-gray-500 font-bold">症状から原因を診断</p>
                        </div>
                    </a>
                    <a href="{{ route('bikes.search') }}" class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-sm transition-all">
                        <i data-lucide="search" class="w-5 h-5 text-blue-500"></i>
                        <div>
                            <p class="text-xs font-black text-gray-800">中古バイクを探す</p>
                            <p class="text-[10px] text-gray-500 font-bold">全国の在庫を検索</p>
                        </div>
                    </a>
                    <a href="{{ route('parts.index') }}" class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-sm transition-all">
                        <i data-lucide="wrench" class="w-5 h-5 text-blue-500"></i>
                        <div>
                            <p class="text-xs font-black text-gray-800">パーツを探す</p>
                            <p class="text-[10px] text-gray-500 font-bold">パーツ検索</p>
                        </div>
                    </a>
                </div>
            </section>

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
