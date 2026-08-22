<x-layout>
    <x-slot:title>バイクのタイヤサイズ一覧 | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクのタイヤサイズ（前輪）別に適合車種を一覧できるインデックス。サイズごとの該当車種数を掲載。純正装着サイズから同じタイヤを履く車種を探せます。</x-slot:metaDescription>

    <x-slot:styles>
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "BreadcrumbList",
            "itemListElement": [
                {"@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}"},
                {"@@type": "ListItem", "position": 2, "name": "バイク検索", "item": "{{ route('bikes.search') }}"},
                {"@@type": "ListItem", "position": 3, "name": "タイヤサイズ一覧", "item": "{{ url()->current() }}"}
            ]
        }
        </script>
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search') }}" class="hover:text-gray-600 transition-colors">バイク検索</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">タイヤサイズ一覧</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">バイクのタイヤサイズ一覧</h1>
                <p class="text-sm text-gray-500 leading-relaxed">前輪の純正装着サイズ別に、適合する車種をまとめました。サイズを選ぶと、そのタイヤを履く車種の一覧・中古在庫・相場が見られます。</p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            @if(empty($sizes))
                <p class="text-sm text-gray-500">現在、掲載条件を満たすタイヤサイズがありません。</p>
            @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($sizes as $s)
                <a href="{{ route('bikes.tire_size.show', ['sizeSlug' => $s['size_slug']]) }}"
                   class="block rounded-xl bg-white border border-gray-100 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-black text-gray-900">{{ $s['size'] }}</span>
                        <span class="text-[11px] font-bold text-gray-400">{{ number_format((int) $s['count']) }}車種</span>
                    </div>
                    {{-- 代表画像（在庫多い順・画像有りのみ最大3枚）。1枚も無ければ領域自体を出さない。 --}}
                    @if(! empty($s['images']))
                    <div class="mt-3 grid grid-cols-3 gap-1.5">
                        @foreach($s['images'] as $img)
                        <div class="aspect-[4/3] rounded-lg overflow-hidden bg-gray-50">
                            <img src="{{ $img['url'] }}" alt="{{ $img['name'] }}" width="120" height="90"
                                 class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </div>
                        @endforeach
                    </div>
                    @endif
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</x-layout>
