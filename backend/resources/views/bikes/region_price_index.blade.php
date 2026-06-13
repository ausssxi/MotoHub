<x-layout>
    <x-slot:title>エリア別 中古相場・地域差が大きい車種 | MotoHub</x-slot:title>
    <x-slot:metaDescription>中古バイクの相場は地域で差が出ます。全国8エリアの中央値を比較し、地域差が大きい車種を一覧。安く買えるエリアを車種ごとにチェックできます。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-4xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">エリア別相場</span></li>
                    </ol>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    エリア別 中古相場<span class="text-lg text-gray-400 ml-2">地域差が大きい車種</span>
                </h1>
                <p class="text-sm text-gray-500 leading-relaxed max-w-3xl">
                    中古バイクの相場は地域で差が出ます。全国8エリアの中央値を比較して地域差が大きい車種をまとめました。安く買えるエリアを車種ごとに確認できます。
                </p>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-8">
            @forelse($models as $row)
            <a href="{{ route('bikes.region_price', $row['model']->slug) }}" class="flex items-center justify-between bg-white hover:bg-indigo-50 rounded-xl p-4 mb-3 border border-gray-100 hover:border-indigo-200 shadow-sm transition-colors group">
                <div class="min-w-0">
                    <div class="font-black text-sm text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $row['model']->name }}</div>
                    <div class="text-xs font-bold text-gray-400 mt-0.5">
                        最安 {{ $row['spread']['low']['block'] }}（{{ $row['spread']['low']['median_man'] }}万）／最高 {{ $row['spread']['high']['block'] }}（{{ $row['spread']['high']['median_man'] }}万）
                    </div>
                </div>
                <div class="text-right shrink-0 ml-3">
                    <div class="text-lg font-black text-indigo-600">{{ $row['spread']['pct'] }}%</div>
                    <div class="text-[10px] font-bold text-gray-400">地域差</div>
                </div>
            </a>
            @empty
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100">
                <p class="text-sm font-bold text-gray-400">対象の車種は準備中です。</p>
            </div>
            @endforelse
        </div>
    </div>
</x-layout>
