<x-layout>
    <x-slot:title>道の駅 座標入力（管理） | MotoHub</x-slot:title>
    <x-slot:metaDescription>座標未設定の道の駅を地図クリックで入力する管理画面。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #rs-coord-stage { height: calc(100vh - 8rem); }
            #rs-map { height: 100%; z-index: 10; border-radius: 12px; }
            .rs-row.selected { background:#ede9fe; border-color:#7c3aed; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/admin/roadside-coordinates.js') }}?v={{ asset_buster(public_path('js/admin/roadside-coordinates.js')) }}"></script>
    </x-slot:scripts>

    <x-slot:navigation><x-navigation :showSearch="false" /></x-slot:navigation>

    @php
        $prefs = $stations->pluck('prefecture')->filter()->unique()->sort()->values();
    @endphp

    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-4"
         id="rs-coord-app"
         data-update-url="{{ route('admin.roadside-stations.coordinates.update', ['station' => 'STATION_ID']) }}"
         data-csrf="{{ csrf_token() }}">

        <div class="flex items-center justify-between mb-3">
            <h1 class="text-lg font-black text-gray-900 flex items-center gap-2">
                <i data-lucide="map-pin" class="w-5 h-5 text-violet-600"></i> 道の駅 座標入力
            </h1>
            <p class="text-sm font-bold text-gray-600">残り <span id="rs-remaining" class="text-violet-600">{{ $remaining }}</span> 件 / 全 {{ number_format($total) }} 件</p>
        </div>

        <div id="rs-coord-stage" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 左: 未設定一覧（ここだけスクロール） --}}
            <div class="flex flex-col min-h-0 bg-white rounded-2xl border border-gray-100 p-3">
                <select id="rs-pref-filter" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 shrink-0">
                    <option value="">すべての都道府県</option>
                    @foreach($prefs as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <ul id="rs-list" class="flex-1 overflow-y-auto space-y-1.5 pr-1 min-h-0">
                    @foreach($stations as $s)
                    <li class="rs-row border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 transition text-sm"
                        data-id="{{ $s->id }}"
                        data-station-code="{{ $s->station_code }}"
                        data-name="{{ $s->name }}"
                        data-pref="{{ $s->prefecture }}"
                        data-city="{{ $s->city }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-gray-800 truncate">{{ $s->name }}</span>
                            <span class="text-[10px] text-gray-400 shrink-0">{{ $s->station_code }}</span>
                        </div>
                        <div class="text-[11px] text-gray-500 mt-0.5">
                            {{ $s->prefecture }}{{ $s->city }}
                            @if($s->designated_year) ・{{ $s->designated_year }}年@endif
                            @if($s->website_url)
                            ・<a href="{{ $s->website_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline" onclick="event.stopPropagation();">公式サイト</a>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- 右: 地図＋保存 --}}
            <div class="flex flex-col min-h-0 gap-2">
                <div class="flex-1 min-h-0">
                    <div id="rs-map"></div>
                </div>
                <div class="shrink-0 bg-white rounded-2xl border border-gray-100 p-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <p class="text-xs text-gray-600">選択中: <span id="rs-selected-name" class="font-bold text-gray-900">（未選択）</span></p>
                        <p class="text-xs text-gray-500">緯度経度: <span id="rs-coord-display" class="font-mono">-</span></p>
                    </div>
                    <p id="rs-error" class="text-xs text-red-600 mb-2 hidden"></p>
                    <button id="rs-save" type="button" disabled
                            class="w-full bg-violet-600 text-white font-bold py-2.5 rounded-xl hover:bg-violet-700 transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                        座標を保存
                    </button>
                    <p class="text-[10px] text-gray-400 mt-1">地図をクリックしてピンを置き、必要ならドラッグで微調整して保存してください。</p>

                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-[11px] font-bold text-gray-400 mb-1">保存済み（押し間違い確認用）</p>
                        <ul id="rs-saved" class="text-[11px] text-gray-600 space-y-0.5 max-h-24 overflow-y-auto"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>
