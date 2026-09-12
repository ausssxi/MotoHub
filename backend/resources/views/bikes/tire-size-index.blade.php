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
            @if(empty($groups))
                <p class="text-sm text-gray-500">現在、掲載条件を満たすタイヤサイズがありません。</p>
            @else
            <div class="space-y-10">
                @foreach($groups as $group)
                <section>
                    {{-- リム径グループの見出し＋説明。説明が「自分は17インチかも」の入口になる。 --}}
                    <div class="flex items-baseline gap-3 mb-4">
                        <h2 class="text-lg font-black text-gray-900">{{ $group['label'] }}</h2>
                        <span class="text-[11px] font-bold text-gray-400">{{ number_format((int) $group['count']) }}サイズ</span>
                    </div>
                    @if($group['desc'] !== '')
                    <p class="text-xs text-gray-500 -mt-3 mb-4">{{ $group['desc'] }}</p>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($group['sizes'] as $s)
                        <a href="{{ route('bikes.tire_size.show', ['sizeSlug' => $s['size_slug']]) }}"
                           class="flex items-start gap-3 rounded-xl bg-white border border-gray-100 p-4 hover:shadow-md transition-shadow">
                            {{-- サーバー側で描いた同心円のタイヤ断面図（共通縮尺）。描けないサイズは枠ごと省略。 --}}
                            @if(! empty($s['svg']))
                            <span class="shrink-0 w-[92px] h-[92px]">{!! $s['svg'] !!}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-black text-gray-900">{{ $s['size'] }}</span>
                                @if(! empty($s['dim_text']))
                                <span class="block text-[11px] text-gray-500 mt-0.5">{{ $s['dim_text'] }}</span>
                                @endif
                                <span class="block text-[11px] font-bold text-gray-400 mt-1">{{ number_format((int) $s['count']) }}車種</span>
                                @if(! empty($s['names']))
                                <span class="block text-xs text-gray-600 mt-1 line-clamp-2">{{ implode('、', $s['names']) }}{{ $s['more'] ? ' ほか' : '' }}</span>
                                @endif
                            </span>
                        </a>
                        @endforeach
                    </div>
                </section>
                @endforeach
            </div>
            @endif

            {{-- 出口導線（行き止まり回避）。models.blade と同種の「〜から探す」リンク。 --}}
            <div class="mt-10 pt-6 border-t border-gray-100">
                <h2 class="text-sm font-black text-gray-700 mb-4">ほかの探し方</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('bikes.models') }}" class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">車種カタログ</a>
                    <a href="{{ route('bikes.search', ['min_displacement' => 51, 'max_displacement' => 125]) }}" class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">排気量から探す</a>
                    <a href="{{ route('bikes.category_type', ['slug' => 'naked']) }}" class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">タイプから探す</a>
                    <a href="{{ route('bikes.prefectures') }}" class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">地域から探す</a>
                    <a href="{{ route('bikes.search') }}" class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">中古バイクを検索</a>
                </div>
            </div>
        </div>
    </div>
</x-layout>
