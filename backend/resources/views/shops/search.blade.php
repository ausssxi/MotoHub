<x-layout>
    <x-slot:title>「{{ $rawQ }}」の店名検索結果｜バイクショップ - MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubに掲載されているバイクショップを店名で検索できます。</x-slot:metaDescription>
    {{-- 内部検索結果は薄いページの量産になるため必ず noindex --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        // 投稿導線に引き継ぐクエリ（pref は指定があるときだけ）
        $submitParams = array_filter(['name' => $rawQ, 'pref' => $pref], fn ($v) => $v !== '');
    @endphp

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.area.index') }}" class="hover:text-gray-600 transition-colors">バイクショップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">店名検索</span></li>
                </ol>
            </nav>

            {{-- 検索ボックス（現在値を保持） --}}
            <x-shop-name-search :q="$rawQ" :pref="$pref" :type="$type" />

            @if($tooShort)
                {{-- 2文字未満: エラーにせず案内のみ --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i data-lucide="search" class="w-8 h-8 text-gray-300 mx-auto mb-3"></i>
                    <p class="text-sm font-bold text-gray-500">店名を{{ $minLength }}文字以上で入力してください。</p>
                </div>
            @else
                {{-- 件数エコー --}}
                <div class="flex items-baseline justify-between mb-4">
                    <p class="text-sm font-bold text-gray-600">
                        「<span class="font-black text-gray-900">{{ $rawQ }}</span>」の検索結果
                        <span class="text-gray-400">（{{ number_format($shops->total()) }}件）</span>
                    </p>
                </div>

                @if($shops->total() === 0)
                    {{-- ★ ゼロヒット → 投稿導線（本丸） --}}
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 sm:p-8 text-center">
                        <i data-lucide="store" class="w-10 h-10 text-emerald-500 mx-auto mb-4"></i>
                        <p class="text-base font-black text-gray-800 mb-2">
                            「{{ $rawQ }}」に一致するお店は見つかりませんでした
                        </p>
                        <p class="text-sm text-gray-500 font-bold mb-5">
                            この店をご存知ですか？ 情報を投稿してMotoHubに掲載しましょう。
                        </p>
                        {{-- bg-green-600 は本番のコンパイル済みCSSに存在（bg-emerald-600 はパージ済み＝無背景で押せなさそうに見えた） --}}
                        <a href="{{ route('shops.submit.create', $submitParams) }}"
                           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-black text-sm px-6 py-3 rounded-xl transition active:scale-[0.99]">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            このお店の情報を投稿する
                        </a>
                    </div>
                @else
                    {{-- ショップカード一覧 --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($shops as $shop)
                        <a href="{{ route('shops.show', $shop) }}"
                           class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-blue-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block overflow-hidden">
                            @if($shop->display_image_url)
                            <div class="aspect-[16/9] bg-gray-200 overflow-hidden">
                                <img src="{{ $shop->display_image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            @endif
                            <div class="p-5">
                                <div class="flex items-start justify-between mb-2 gap-2">
                                    <h3 class="text-sm font-black text-gray-900 group-hover:text-blue-700 transition-colors line-clamp-2 flex-1">{{ $shop->name }}</h3>
                                    @if($shop->shop_type === 'repair_only')
                                    <span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-100">
                                        <i data-lucide="wrench" class="w-2.5 h-2.5"></i>整備・修理
                                    </span>
                                    @elseif($shop->shop_type === 'dealer')
                                    <span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                                        <i data-lucide="store" class="w-2.5 h-2.5"></i>販売店
                                    </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-400 line-clamp-1 mb-3">{{ $shop->prefecture }}{{ $shop->city }}</p>
                                @if(!empty($shop->service_tags))
                                <div class="mb-3">
                                    <x-shop-service-tags :tags="$shop->service_tags" />
                                </div>
                                @endif
                                <div class="flex items-center justify-between text-xs">
                                    @if($shop->phone)
                                    <span class="font-bold text-gray-600">{{ $shop->phone }}</span>
                                    @else
                                    <span></span>
                                    @endif
                                    <span class="text-gray-300 group-hover:text-blue-500 transition-colors">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>

                    {{-- ページネーション --}}
                    <div class="mt-8">
                        {{ $shops->links() }}
                    </div>

                    {{-- 結果ありでも: 部分一致が本命と違うケースの受け皿 --}}
                    <div class="mt-8 text-center">
                        <p class="text-xs text-gray-400 font-bold mb-2">お探しのお店が見つかりませんか？</p>
                        <a href="{{ route('shops.submit.create', $submitParams) }}"
                           class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-600 hover:text-emerald-700 hover:underline">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            このお店の情報を投稿する
                        </a>
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-layout>
