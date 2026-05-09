<x-layout>
    <x-slot:title>お気に入りスポット - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-8">
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1 mb-3">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i> マイページに戻る
                </a>
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2">
                    <i data-lucide="star" class="w-6 h-6 text-amber-500"></i>
                    お気に入りスポット
                </h1>
                <p class="text-xs font-bold text-gray-500 mt-1 ml-8">保存したスポット {{ $spots->count() }}件</p>
            </div>

            @if($spots->isEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
                    <i data-lucide="map-pin-off" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                    <p class="text-sm text-gray-500 mb-4">まだスポットを保存していません</p>
                    <a href="{{ route('riders.map') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-5 py-3 rounded-xl text-sm font-black hover:bg-amber-600 transition-colors">
                        ライダーズマップで保存する <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            @else
                <div class="space-y-3">
                    @php
                        $categoryLabels = [
                            'touring_spot' => 'ツーリングスポット',
                            'rest_area' => '休憩所',
                            'scenic_point' => '絶景ポイント',
                            'other' => 'その他',
                        ];
                        $categoryColors = [
                            'touring_spot' => 'bg-blue-50 text-blue-700',
                            'rest_area' => 'bg-green-50 text-green-700',
                            'scenic_point' => 'bg-purple-50 text-purple-700',
                            'other' => 'bg-gray-100 text-gray-600',
                        ];
                    @endphp
                    @foreach($spots as $spot)
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow" id="spot-{{ $spot->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="text-sm font-black text-gray-900 truncate">{{ $spot->name }}</h3>
                                        <span class="shrink-0 px-2 py-0.5 text-[10px] font-bold rounded {{ $categoryColors[$spot->category] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $categoryLabels[$spot->category] ?? 'その他' }}
                                        </span>
                                    </div>
                                    @if($spot->memo)
                                        <p class="text-xs text-gray-500 mb-1.5">{{ $spot->memo }}</p>
                                    @endif
                                    <p class="text-[10px] text-gray-400">{{ $spot->created_at->format('Y.m.d H:i') }}</p>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <a href="{{ route('riders.map', ['lat' => $spot->latitude, 'lng' => $spot->longitude, 'zoom' => 15]) }}"
                                       class="px-2.5 py-1.5 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-lg hover:bg-amber-100 transition">
                                        マップで見る
                                    </a>
                                    <a href="https://www.google.com/maps?q={{ $spot->latitude }},{{ $spot->longitude }}" target="_blank" rel="noopener"
                                       class="px-2.5 py-1.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition">
                                        Google Map
                                    </a>
                                    <button onclick="deleteSpot({{ $spot->id }})"
                                            class="px-2.5 py-1.5 bg-red-50 text-red-600 text-[10px] font-bold rounded-lg hover:bg-red-100 transition">
                                        削除
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 text-center">
                    <a href="{{ route('riders.map') }}" class="inline-flex items-center gap-2 text-sm font-bold text-blue-600 hover:underline">
                        ライダーズマップを開く <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <x-slot:scripts>
        <script>
            function deleteSpot(id) {
                if (!confirm('このスポットを削除しますか？')) return;
                fetch('/api/spots/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                })
                .then(function(r) { return r.json(); })
                .then(function() {
                    var el = document.getElementById('spot-' + id);
                    if (el) el.remove();
                })
                .catch(function(e) { alert('削除に失敗しました'); });
            }
        </script>
    </x-slot:scripts>
</x-layout>
