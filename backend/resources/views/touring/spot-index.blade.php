<x-layout>
    <x-slot:title>{{ $prefName }}のツーリングスポット | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefName }}のバイクツーリングにおすすめのスポット・ルートを紹介。絶景ロードや峠など、ライダーに人気のツーリングスポット一覧。</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/touring/' . $prefectureSlug) }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- パンくずリスト --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('touring.index') }}" class="hover:text-gray-600">ツーリング</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ $prefName }}</span>
        </nav>

        <h1 class="text-2xl font-bold mb-2">{{ $prefName }}のツーリングスポット</h1>
        <p class="text-gray-500 text-sm mb-8">{{ $prefName }}でバイクツーリングにおすすめのスポット・絶景ルートをまとめました。</p>

        {{-- 都道府県ナビ --}}
        @if($allPrefectures->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-8">
                @foreach($allPrefectures as $pref)
                    <a href="{{ url('/touring/' . $pref['slug']) }}"
                       class="px-3 py-1.5 text-xs font-bold rounded-full transition {{ $pref['slug'] === $prefectureSlug ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $pref['name'] }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- スポット一覧 --}}
        <div class="space-y-4">
            @forelse($spots as $spot)
                <a href="{{ url('/touring/' . $prefectureSlug . '/' . $spot->slug) }}"
                   class="block bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        @if($spot->difficulty)
                            @php
                                $difficultyColors = [
                                    '初級' => 'bg-green-100 text-green-700',
                                    '中級' => 'bg-yellow-100 text-yellow-700',
                                    '上級' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded {{ $difficultyColors[$spot->difficulty] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $spot->difficulty }}
                            </span>
                        @endif
                        @if($spot->recommended_season)
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded bg-cyan-100 text-cyan-700">
                                {{ $spot->recommended_season }}
                            </span>
                        @endif
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 mb-1.5">{{ $spot->name }}</h2>
                    @if($spot->description)
                        <p class="text-sm text-gray-500 line-clamp-2 mb-2">{{ $spot->description }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                        @if($spot->distance_km)
                            <span>{{ $spot->distance_km }}km</span>
                        @endif
                        @if($spot->highlights)
                            @foreach(array_slice($spot->highlights, 0, 3) as $hl)
                                <span class="bg-gray-50 px-1.5 py-0.5 rounded">{{ $hl }}</span>
                            @endforeach
                        @endif
                    </div>
                </a>
            @empty
                <p class="text-center text-gray-400 py-12">{{ $prefName }}のツーリングスポットはまだ登録されていません。</p>
            @endforelse
        </div>
    </div>
</x-layout>
