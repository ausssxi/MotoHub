<x-layout>
    <x-slot:title>{{ $spot->name }} | {{ $prefName }}ツーリングスポット | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $spot->description }}</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/touring/' . $prefectureSlug . '/' . $spot->slug) }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "TouristAttraction",
            "name": "{{ e($spot->name) }}",
            "description": "{{ e($spot->description) }}",
            "geo": {
                "@@type": "GeoCoordinates",
                "latitude": {{ $spot->lat }},
                "longitude": {{ $spot->lng }}
            },
            "isAccessibleForFree": true,
            "address": {
                "@@type": "PostalAddress",
                "addressRegion": "{{ e($prefName) }}",
                "addressCountry": "JP"
            }
        }
        </script>
    </x-slot:styles>

    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- パンくずリスト --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('touring.index') }}" class="hover:text-gray-600">ツーリング</a>
            <span class="mx-1">/</span>
            <a href="{{ url('/touring/' . $prefectureSlug) }}" class="hover:text-gray-600">{{ $prefName }}</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ Str::limit($spot->name, 30) }}</span>
        </nav>

        <article>
            {{-- ヘッダー --}}
            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 leading-tight mb-4">
                    {{ $spot->name }}
                </h1>

                {{-- 情報バッジ --}}
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">
                        {{ $prefName }}
                    </span>
                    @if($spot->difficulty)
                        @php
                            $difficultyColors = [
                                '初級' => 'bg-green-100 text-green-700',
                                '中級' => 'bg-yellow-100 text-yellow-700',
                                '上級' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full {{ $difficultyColors[$spot->difficulty] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $spot->difficulty }}
                        </span>
                    @endif
                    @if($spot->distance_km)
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-700">
                            {{ $spot->distance_km }}km
                        </span>
                    @endif
                    @if($spot->recommended_season)
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-cyan-100 text-cyan-700">
                            {{ $spot->recommended_season }}
                        </span>
                    @endif
                </div>

                @if($spot->description)
                    <p class="text-gray-600 leading-relaxed">{{ $spot->description }}</p>
                @endif
            </header>

            {{-- マップ --}}
            <div class="mb-8 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                <div class="riders-map-embed"
                     data-lat="{{ $spot->lat }}"
                     data-lng="{{ $spot->lng }}"
                     data-zoom="13"
                     data-layers="gas_station,parking,michi_no_eki"
                     style="height:350px; width:100%;"></div>
                <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 text-xs text-gray-500">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ number_format((float) $spot->lat, 5) }}, {{ number_format((float) $spot->lng, 5) }}</span>
                    <a href="{{ route('riders.map') }}?lat={{ $spot->lat }}&lng={{ $spot->lng }}&zoom=14"
                       class="ml-auto text-cyan-600 hover:text-cyan-800 font-medium">ライダーズマップで見る &rarr;</a>
                </div>
            </div>

            {{-- 見どころ --}}
            @if($spot->highlights && count($spot->highlights) > 0)
                <div class="mb-8 bg-amber-50 border border-amber-200 rounded-xl p-5">
                    <h2 class="text-lg font-bold text-gray-900 mb-3">見どころ</h2>
                    <ul class="space-y-2">
                        @foreach($spot->highlights as $highlight)
                            <li class="flex items-start gap-2 text-sm text-gray-700">
                                <svg class="w-4 h-4 text-amber-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                {{ $highlight }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 詳細コンテンツ --}}
            @if($spot->content)
                <div class="mb-8 prose prose-gray max-w-none">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">詳細情報</h2>
                    <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $spot->content }}</p>
                </div>
            @endif

            {{-- 基本情報テーブル --}}
            <div class="mb-8 bg-white border border-gray-200 rounded-xl overflow-hidden">
                <h2 class="text-lg font-bold text-gray-900 px-5 py-3 bg-gray-50 border-b border-gray-200">基本情報</h2>
                <table class="w-full text-sm">
                    <tbody>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-3 text-gray-500 font-medium w-1/3">所在地</th>
                            <td class="px-5 py-3 text-gray-900">{{ $prefName }}</td>
                        </tr>
                        @if($spot->difficulty)
                            <tr class="border-b border-gray-100">
                                <th class="text-left px-5 py-3 text-gray-500 font-medium">難易度</th>
                                <td class="px-5 py-3 text-gray-900">{{ $spot->difficulty }}</td>
                            </tr>
                        @endif
                        @if($spot->distance_km)
                            <tr class="border-b border-gray-100">
                                <th class="text-left px-5 py-3 text-gray-500 font-medium">走行距離目安</th>
                                <td class="px-5 py-3 text-gray-900">約{{ $spot->distance_km }}km</td>
                            </tr>
                        @endif
                        @if($spot->recommended_season)
                            <tr class="border-b border-gray-100">
                                <th class="text-left px-5 py-3 text-gray-500 font-medium">おすすめ時期</th>
                                <td class="px-5 py-3 text-gray-900">{{ $spot->recommended_season }}</td>
                            </tr>
                        @endif
                        <tr>
                            <th class="text-left px-5 py-3 text-gray-500 font-medium">座標</th>
                            <td class="px-5 py-3 text-gray-900">{{ number_format((float) $spot->lat, 5) }}, {{ number_format((float) $spot->lng, 5) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- 周辺リンク --}}
            <div class="mb-8 flex flex-wrap gap-3">
                <a href="{{ route('riders.map') }}?lat={{ $spot->lat }}&lng={{ $spot->lng }}&zoom=13"
                   class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 transition text-sm font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    ライダーズマップで周辺を見る
                </a>
                <a href="{{ url('/parking?lat=' . $spot->lat . '&lng=' . $spot->lng) }}"
                   class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-200 transition text-sm font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    周辺の駐車場を探す
                </a>
            </div>

            {{-- シェアボタン --}}
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">共有:</span>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(url('/touring/' . $prefectureSlug . '/' . $spot->slug)) }}&text={{ urlencode($spot->name . ' | ' . $prefName . 'ツーリングスポット') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        Xで共有
                    </a>
                </div>
            </div>
        </article>

        {{-- 関連スポット --}}
        @if($relatedSpots->isNotEmpty())
            <div class="mt-12">
                <h2 class="text-xl font-bold text-gray-900 mb-4">{{ $prefName }}の他のツーリングスポット</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($relatedSpots as $related)
                        <a href="{{ url('/touring/' . $prefectureSlug . '/' . $related->slug) }}"
                           class="block bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-gray-300 transition">
                            <h3 class="font-bold text-gray-900 mb-1">{{ $related->name }}</h3>
                            @if($related->description)
                                <p class="text-sm text-gray-500 line-clamp-2">{{ $related->description }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-gray-400">
                                @if($related->difficulty)
                                    <span>{{ $related->difficulty }}</span>
                                @endif
                                @if($related->distance_km)
                                    <span>{{ $related->distance_km }}km</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/riders/poi-name.js') }}"></script>
        <script src="{{ asset('js/blog/embedded-map.js') }}"></script>
    </x-slot:scripts>
</x-layout>
