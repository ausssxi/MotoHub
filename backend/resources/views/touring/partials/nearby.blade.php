{{--
    ルート周辺の立ち寄り先。0件の種別は見出しごと出さない（空枠・「該当なし」も出さない）。
    施設名（GS/洗車場）は TouringNearby が PoiDisplayResolver で解決済み（$item['display']）。
    4ブロックすべて0件ならセクション全体を描画しない。
--}}
@php($nGas = $nearby['gas'] ?? ['count' => 0, 'sparse' => false, 'items' => []])
@if(! empty($nearby['spots']) || ! empty($nearby['stations']) || ($nGas['count'] ?? 0) > 0 || ! empty($nearby['car_wash']))
<section class="mt-12 pt-8 border-t border-gray-200">
    <h2 class="text-xl font-black text-gray-900 mb-1">このルートの周辺情報</h2>
    <p class="text-sm text-gray-500 mb-6">走りに出る前後の立ち寄り先です。距離は地図の中心からのおおよその直線距離です。</p>

    {{-- 1. ツーリングスポット --}}
    @if(! empty($nearby['spots']))
    <div class="mb-8">
        <h3 class="text-base font-black text-gray-900 mb-3">このルート周辺のツーリングスポット</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($nearby['spots'] as $s)
            @php($tag = $s['url'] ? 'a' : 'div')
            <{{ $tag }} @if($s['url']) href="{{ $s['url'] }}" @endif
                class="flex items-center gap-3 rounded-xl bg-white border border-gray-100 p-3 {{ $s['url'] ? 'hover:shadow-md transition-shadow' : '' }}">
                <span class="shrink-0 w-16 h-12 rounded-lg overflow-hidden bg-gray-50 flex items-center justify-center">
                    @if($s['image_url'])
                    <img src="{{ $s['image_url'] }}" alt="{{ $s['name'] }}" width="64" height="48" class="w-full h-full object-cover" loading="lazy" decoding="async">
                    @else
                    <i data-lucide="mountain" class="w-5 h-5 text-gray-300"></i>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-black text-gray-900 truncate">{{ $s['name'] }}</span>
                    <span class="block text-[11px] text-gray-400 mt-0.5">{{ $s['prefecture'] }}・約{{ $s['distance_km'] }}km</span>
                </span>
            </{{ $tag }}>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 2. 道の駅 --}}
    @if(! empty($nearby['stations']))
    <div class="mb-8">
        <h3 class="text-base font-black text-gray-900 mb-3">ルート沿いの道の駅</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($nearby['stations'] as $st)
            <a href="{{ $st['url'] }}" class="block rounded-xl bg-white border border-gray-100 p-3 hover:shadow-md transition-shadow">
                <span class="block text-sm font-black text-gray-900 truncate">
                    道の駅 {{ $st['name'] }}@if($st['nickname'])<span class="text-xs font-bold text-gray-400">（{{ $st['nickname'] }}）</span>@endif
                </span>
                <span class="block text-[11px] text-gray-400 mt-0.5">{{ $st['city'] }}・約{{ $st['distance_km'] }}km</span>
                {{-- 詳細ページと同じ共有コンポーネント（TouringNearby が算出済みの最大4件ラベル配列を渡す）。 --}}
                @if(! empty($st['badges']))
                <x-michinoeki-facilities :badges="$st['badges']" class="mt-2" />
                @endif
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 3. ガソリンスタンド（少ないルートは件数＋リスト・多いルートは件数のみ） --}}
    @if(($nGas['count'] ?? 0) > 0)
    <div class="mb-8">
        @if($nGas['sparse'])
        <h3 class="text-base font-black text-gray-900 mb-1">このルート周辺のガソリンスタンド（{{ $nGas['count'] }}軒）</h3>
        <p class="text-sm text-gray-600 mb-3">このルートの周辺にあるガソリンスタンドは{{ $nGas['count'] }}軒です。出発前の給油をおすすめします。</p>
        @if(! empty($nGas['items']))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($nGas['items'] as $g)
            <a href="{{ $g['url'] }}" class="block rounded-xl bg-white border border-gray-100 p-3 hover:shadow-md transition-shadow">
                <span class="block text-sm font-black text-gray-900 truncate">{{ $g['display'] }}</span>
                <span class="block text-[11px] text-gray-400 mt-0.5">{{ $g['city'] }}・約{{ $g['distance_km'] }}km</span>
            </a>
            @endforeach
        </div>
        @endif
        @else
        <h3 class="text-base font-black text-gray-900 mb-1">このルート周辺のガソリンスタンド</h3>
        <p class="text-sm text-gray-600 mb-3">このルートの周辺には{{ $nGas['count'] }}軒のガソリンスタンドがあります。</p>
        <a href="{{ route('riders.map') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 hover:underline">
            <i data-lucide="map" class="w-4 h-4"></i>地図でガソリンスタンドを探す
        </a>
        @endif
    </div>
    @endif

    {{-- 4. 洗車場 --}}
    @if(! empty($nearby['car_wash']))
    <div class="mb-2">
        <h3 class="text-base font-black text-gray-900 mb-3">帰りに寄れる洗車場</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($nearby['car_wash'] as $w)
            <a href="{{ $w['url'] }}" class="block rounded-xl bg-white border border-gray-100 p-3 hover:shadow-md transition-shadow">
                <span class="block text-sm font-black text-gray-900 truncate">{{ $w['display'] }}</span>
                <span class="block text-[11px] text-gray-400 mt-0.5">{{ $w['city'] }}・約{{ $w['distance_km'] }}km</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</section>
@endif
