<x-layout>
    <x-slot:title>ツーリングガイド | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクツーリングのおすすめルートガイド。距離・難易度・所要時間付きで初心者から上級者まで楽しめるコースを紹介。</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/touring') }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">ツーリングガイド</h1>

        {{-- 都道府県フィルタ --}}
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="{{ route('touring.index') }}"
               class="px-3 py-1.5 text-xs font-bold rounded-full transition {{ !request('prefecture') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                すべて
            </a>
            @foreach($prefectures as $pref)
                <a href="{{ route('touring.index', ['prefecture' => $pref]) }}"
                   class="px-3 py-1.5 text-xs font-bold rounded-full transition {{ request('prefecture') === $pref ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $pref }}
                </a>
            @endforeach
        </div>

        {{-- ガイド一覧 --}}
        <div class="space-y-4">
            @forelse($guides as $guide)
                <a href="{{ route('touring.show', $guide->slug) }}"
                   class="block bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-700">
                            {{ $guide->prefecture }}
                        </span>
                        @php
                            $difficultyColors = [
                                '初級' => 'bg-green-100 text-green-700',
                                '中級' => 'bg-yellow-100 text-yellow-700',
                                '上級' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded {{ $difficultyColors[$guide->difficulty] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $guide->difficulty }}
                        </span>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 mb-1.5">{{ $guide->title }}</h2>
                    @if($guide->excerpt)
                        <p class="text-sm text-gray-500 line-clamp-2 mb-2">{{ $guide->excerpt }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                        @if($guide->distance_km)
                            <span>{{ $guide->distance_km }}km</span>
                        @endif
                        @if($guide->duration_text)
                            <span>{{ $guide->duration_text }}</span>
                        @endif
                        <span>{{ $guide->reading_time_minutes }}分で読める</span>
                        @if($guide->published_at)
                            <span>{{ $guide->published_at->format('Y.m.d') }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <p class="text-center text-gray-400 py-12">ツーリングガイドはまだありません。</p>
            @endforelse
        </div>

        {{-- ページネーション --}}
        <div class="mt-8">
            {{ $guides->links() }}
        </div>
    </div>
</x-layout>
