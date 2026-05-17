<x-layout>
    <x-slot:title>{{ $prefecture }}の中古バイク｜{{ number_format($totalCount) }}台掲載 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}の中古バイク{{ number_format($totalCount) }}台を一括検索。メーカー別・タイプ別・排気量別に比較できます。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.area_index', $prefecture) }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-5xl mx-auto px-4">

            <div class="text-center mb-16">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-3 tracking-tighter">
                    {{ $prefecture }}の中古バイク
                </h1>
                <p class="text-gray-400 text-sm font-bold">
                    {{ number_format($totalCount) }}台掲載中
                </p>
            </div>

            {{-- メーカーから探す --}}
            @if($makers->isNotEmpty())
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>
                    メーカーから探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach($makers as $maker)
                        <a href="{{ $maker['url'] }}"
                           class="group flex items-center justify-between px-4 py-3 rounded-xl bg-white border border-gray-200 shadow-sm hover:border-blue-500 hover:shadow-md hover:-translate-y-0.5 transition duration-200">
                            <div>
                                <span class="text-sm font-black text-gray-700 group-hover:text-blue-600 transition-colors block">{{ $maker['label'] }}</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ number_format($maker['count']) }}台</span>
                            </div>
                            <div class="w-6 h-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-400 group-hover:text-blue-500 transition-colors"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- タイプから探す --}}
            @if($categories->isNotEmpty())
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-orange-500 rounded-full"></span>
                    タイプから探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach($categories as $cat)
                        <a href="{{ $cat['url'] }}"
                           class="group flex items-center justify-between px-4 py-3 rounded-xl bg-white border border-gray-200 shadow-sm hover:border-orange-500 hover:shadow-md hover:-translate-y-0.5 transition duration-200">
                            <div>
                                <span class="text-sm font-black text-gray-700 group-hover:text-orange-600 transition-colors block">{{ $cat['label'] }}</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ number_format($cat['count']) }}台</span>
                            </div>
                            <div class="w-6 h-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-orange-50 transition-colors">
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-400 group-hover:text-orange-500 transition-colors"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 排気量から探す --}}
            <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10 mb-8">
                <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-purple-500 rounded-full"></span>
                    排気量から探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    @foreach($displacements as $d)
                        <a href="{{ $d['url'] }}"
                           class="group flex items-center justify-between px-4 py-3 rounded-xl bg-white border border-gray-200 shadow-sm hover:border-purple-500 hover:shadow-md hover:-translate-y-0.5 transition duration-200">
                            <div>
                                <span class="text-sm font-black text-gray-700 group-hover:text-purple-600 transition-colors block">{{ $d['label'] }}</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ number_format($d['count']) }}台</span>
                            </div>
                            <div class="w-6 h-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-purple-50 transition-colors">
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-400 group-hover:text-purple-500 transition-colors"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- 全件検索リンク --}}
            <div class="text-center mt-10">
                <a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}"
                   class="inline-flex items-center gap-2 px-8 py-3 bg-black text-white text-sm font-black rounded-full hover:bg-gray-800 transition-colors">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    {{ $prefecture }}のバイクをすべて見る
                </a>
            </div>

            {{-- 戻るリンク --}}
            <div class="mt-20 pt-10 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.prefectures') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> すべての地域を見る
                </a>
            </div>

        </div>
    </div>
</x-layout>
