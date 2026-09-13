<x-layout>
    <x-slot:title>バイクで行く{{ $keyword }}ツーリングスポット | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国の{{ $keyword }}が楽しめるツーリングスポット{{ number_format($total) }}箇所を、見頃の時期がわかる場所・エリア別にまとめました。見頃はおおむね10月下旬〜11月上旬です。</x-slot:metaDescription>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- 1. 見出し＋リード --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('touring.index') }}" class="hover:text-gray-600 transition-colors">ツーリングガイド</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $keyword }}ツーリング</span></li>
                    </ol>
                </nav>

                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">バイクで行く{{ $keyword }}ツーリングスポット</h1>
                <div class="text-sm text-gray-600 leading-relaxed space-y-1">
                    <p>全国の{{ $keyword }}が楽しめるツーリングスポットを<strong class="text-gray-900">{{ number_format($total) }}箇所</strong>集めました。見頃はおおむね10月下旬〜11月上旬です。</p>
                    <p class="text-gray-500">山間部は朝晩が冷え、落ち葉で路面が滑りやすくなります。防寒と、下り・日陰のペースにご注意ください。</p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8 space-y-12">
            {{-- 2. 見頃の時期がわかるスポット（スコア3・最重要＝大きく） --}}
            @if(! empty($score3))
            <section>
                <h2 class="text-xl font-black text-gray-900 mb-1">見頃の時期がわかるスポット</h2>
                <p class="text-sm text-gray-500 mb-5">見頃の時期まで分かっている、確度の高いスポットです。</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($score3 as $s)
                    @php($tag = $s['url'] ? 'a' : 'div')
                    {{-- image_url は現状 全件空のため画像枠は描かない（横幅はテキストに回す）。
                         将来 image_url が入ったら、ここにサムネイルを復活させる。 --}}
                    <{{ $tag }} @if($s['url']) href="{{ $s['url'] }}" @endif
                        class="block rounded-2xl bg-white border border-gray-200 p-4 {{ $s['url'] ? 'hover:shadow-md transition-shadow' : '' }}">
                        <span class="block text-base font-black text-gray-900">{{ $s['name'] }}</span>
                        <span class="block text-[11px] font-bold text-gray-400 mt-0.5">{{ $s['prefecture'] }}</span>
                        @if($s['season'])
                        <span class="inline-block mt-2 px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 text-[11px] font-bold">{{ $s['season'] }}</span>
                        @endif
                        @if($s['description'])
                        <span class="block text-xs text-gray-600 mt-2 line-clamp-2">{{ $s['description'] }}</span>
                        @endif
                    </{{ $tag }}>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 3. エリア別（スコア2）。0件エリアは見出しごと非表示。 --}}
            @foreach($score2_areas as $area)
            <section>
                <h2 class="text-lg font-black text-gray-900 mb-4">{{ $area['region'] }}の{{ $keyword }}スポット</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($area['items'] as $s)
                    @php($tag = $s['url'] ? 'a' : 'div')
                    {{-- image_url は現状 全件空のため画像枠は描かない。将来入ったらサムネイルを復活。 --}}
                    <{{ $tag }} @if($s['url']) href="{{ $s['url'] }}" @endif
                        class="block rounded-xl bg-white border border-gray-100 p-3 {{ $s['url'] ? 'hover:shadow-md transition-shadow' : '' }}">
                        <span class="block text-sm font-black text-gray-900 truncate">{{ $s['name'] }}</span>
                        <span class="block text-[11px] font-bold text-gray-400 mt-0.5">{{ $s['prefecture'] }}</span>
                        @if($s['description'])
                        <span class="block text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $s['description'] }}</span>
                        @endif
                    </{{ $tag }}>
                    @endforeach
                </div>
            </section>
            @endforeach

            {{-- 4. ほかにも紅葉が楽しめるスポット（スコア1・テキストリンクの軽い一覧） --}}
            @if(! empty($score1))
            <section>
                <h2 class="text-lg font-black text-gray-900 mb-1">ほかにも{{ $keyword }}が楽しめるスポット</h2>
                <p class="text-sm text-gray-500 mb-4">本文で{{ $keyword }}に触れているスポットです。</p>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach($score1 as $s)
                    @if($s['url'])
                    <a href="{{ $s['url'] }}" class="text-sm text-blue-600 hover:underline">{{ $s['name'] }}<span class="text-gray-400 text-xs">（{{ $s['prefecture'] }}）</span></a>
                    @else
                    <span class="text-sm text-gray-700">{{ $s['name'] }}<span class="text-gray-400 text-xs">（{{ $s['prefecture'] }}）</span></span>
                    @endif
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 5. 秋におすすめのツーリングガイド。0本なら非表示。 --}}
            @if(! empty($guides))
            <section>
                <h2 class="text-lg font-black text-gray-900 mb-4">秋におすすめのツーリングガイド</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($guides as $g)
                    <a href="{{ $g['url'] }}" class="block rounded-xl bg-white border border-gray-100 p-4 hover:shadow-md transition-shadow">
                        <span class="block text-sm font-black text-gray-900">{{ $g['title'] }}</span>
                        @if($g['best_season'])
                        <span class="inline-block mt-2 px-2 py-0.5 rounded-md bg-orange-50 text-orange-700 text-[11px] font-bold">{{ $g['best_season'] }}</span>
                        @endif
                    </a>
                    @endforeach
                </div>
            </section>
            @endif
        </div>
    </div>
</x-layout>
