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

        {{-- 都道府県別ツーリングスポット --}}
        @php
            $slugMap = \App\Models\TouringSpot::PREFECTURE_SLUG_MAP;
            $slugFlip = array_flip($slugMap);
            $regions = [
                '北海道・東北' => ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
                '関東' => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
                '中部' => ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
                '関西' => ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
                '中国・四国' => ['鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県'],
                '九州・沖縄' => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
            ];
        @endphp

        <section class="mt-12">
            <h2 class="text-xl font-bold mb-6">都道府県別ツーリングスポット</h2>

            <div class="space-y-6">
                @foreach($regions as $regionName => $prefs)
                    <div>
                        <h3 class="text-sm font-bold text-gray-600 mb-2">{{ $regionName }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($prefs as $pref)
                                @php
                                    $count = $spotCounts[$pref] ?? 0;
                                    $slug = $slugFlip[$pref] ?? null;
                                @endphp
                                @if($count > 0 && $slug)
                                    <a href="{{ url('/touring/' . $slug) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-full bg-cyan-50 text-cyan-700 hover:bg-cyan-100 transition">
                                        {{ $pref }}
                                        <span class="text-[10px] text-cyan-500">({{ $count }})</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-full bg-gray-50 text-gray-300">
                                        {{ $pref }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layout>
