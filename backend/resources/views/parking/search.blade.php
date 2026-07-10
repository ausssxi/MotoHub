<x-layout>
    <x-slot:title>「{{ $rawQ }}」の駐車場名検索結果｜バイク駐車場 - MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubに登録されているバイク駐車場・駐輪場を駐車場名で検索できます。</x-slot:metaDescription>
    {{-- 内部検索結果は薄いページの量産になるため必ず noindex --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

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
                    <li><a href="{{ route('parking.area.index') }}" class="hover:text-gray-600 transition-colors">バイク駐車場</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">駐車場名検索</span></li>
                </ol>
            </nav>

            {{-- 検索ボックス（現在値を保持） --}}
            <x-parking-name-search :q="$rawQ" />

            @if($tooShort)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i data-lucide="search" class="w-8 h-8 text-gray-300 mx-auto mb-3"></i>
                    <p class="text-sm font-bold text-gray-500">駐車場名を{{ $minLength }}文字以上で入力してください。</p>
                </div>
            @else
                <div class="flex items-baseline justify-between mb-4">
                    <p class="text-sm font-bold text-gray-600">
                        「<span class="font-black text-gray-900">{{ $rawQ }}</span>」の検索結果
                        <span class="text-gray-400">（{{ number_format($parkings->total()) }}件）</span>
                    </p>
                </div>

                @if($parkings->total() === 0)
                    {{-- ゼロヒット → 登録導線（マップと同じ登録フローへ・auth必須） --}}
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 sm:p-8 text-center">
                        <i data-lucide="square-parking" class="w-10 h-10 text-emerald-500 mx-auto mb-4"></i>
                        <p class="text-base font-black text-gray-800 mb-2">
                            「{{ $rawQ }}」に一致する駐車場は見つかりませんでした
                        </p>
                        <p class="text-sm text-gray-500 font-bold mb-5">
                            この駐車場をご存知ですか？ 登録してMotoHubに掲載しましょう。
                        </p>
                        {{-- bg-green-600 はコンパイル済みCSSに存在（bg-emerald-600 はパージ済み＝無背景で押せなさそうに見えた） --}}
                        <a href="{{ route('parking.create') }}"
                           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-black text-sm px-6 py-3 rounded-xl transition active:scale-[0.99]">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            駐車場を登録する
                        </a>
                    </div>
                @else
                    {{-- 駐車場カード一覧 --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($parkings as $parking)
                        <a href="{{ route('parking.show', $parking) }}"
                           class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-green-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block p-5">
                            <div class="flex items-start justify-between mb-2 gap-2">
                                <h3 class="text-sm font-black text-gray-900 group-hover:text-green-700 transition-colors line-clamp-2 flex-1">{{ $parking->name }}</h3>
                                @if($parking->reviews_count > 0)
                                <span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100">
                                    <i data-lucide="star" class="w-2.5 h-2.5"></i>{{ number_format($parking->avg_rating, 1) }}
                                </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 line-clamp-1 mb-3">{{ $parking->prefecture }}{{ $parking->city }}{{ $parking->address ? ' / '.$parking->address : '' }}</p>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-gray-600">{{ $parking->getPriceDisplay() }}</span>
                                <span class="text-gray-300 group-hover:text-green-500 transition-colors">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </span>
                            </div>
                        </a>
                        @endforeach
                    </div>

                    <div class="mt-8">{{ $parkings->links() }}</div>

                    {{-- 結果ありでも: 目的の駐車場が無いケースの受け皿（登録導線） --}}
                    <div class="mt-8 text-center">
                        <p class="text-xs text-gray-400 font-bold mb-2">お探しの駐車場が見つかりませんか？</p>
                        <a href="{{ route('parking.create') }}"
                           class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-600 hover:text-emerald-700 hover:underline">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            駐車場を登録する
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layout>
