<x-layout>
    <x-slot:title>マイコンテンツ（投稿履歴） - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    @php
        $nonEmpty = collect($sections)->filter(fn ($s) => $s['count'] > 0)->values();
        $firstTab = $nonEmpty->first()['type'] ?? null;
    @endphp

    <div class="bg-gray-50 min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6">
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1 mb-3">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i> マイページに戻る
                </a>
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-2">
                    <i data-lucide="library" class="w-6 h-6 text-blue-600"></i>
                    マイコンテンツ
                </h1>
                <p class="text-xs font-bold text-gray-500 mt-1 ml-8">あなたが書いた投稿 合計 {{ number_format($total) }} 件</p>
            </div>

            @if(session('contribution_deleted'))
            <div class="mb-4 text-sm font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">削除しました。</div>
            @endif

            {{-- マップ保存スポット：フィルタタブではなく別ページへのリンク（タブと視覚的に分ける・控えめ） --}}
            <div class="flex justify-end mb-3">
                <a href="{{ route('mypage.saved_spots') }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 hover:text-blue-600 transition-colors">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-amber-500"></i>マップ保存スポット<span class="font-black text-amber-600">{{ $savedSpotsCount }}</span>件<i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            @if($firstTab === null)
                {{-- 全種別0件：控えめな空状態（過疎に見せない・書く導線） --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
                    <i data-lucide="pen-line" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                    <p class="text-sm text-gray-600 font-bold mb-1">まだ投稿はありません。</p>
                    <p class="text-xs text-gray-500 mb-4">レビューやクチコミを書くと、ここに積み上がっていきます。</p>
                    <a href="{{ route('bikes.reviews_index') }}" class="inline-flex items-center gap-1 text-xs font-bold bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors">レビューを見る・書く</a>
                </div>
            @else
                <div x-data="{ tab: '{{ $firstTab }}' }">
                    {{-- タブ（0件の種別は出さない） --}}
                    <div class="flex gap-1.5 mb-4 overflow-x-auto scrollbar-hide">
                        @foreach($sections as $s)
                            @if($s['count'] > 0)
                            <button type="button" @click="tab = '{{ $s['type'] }}'"
                                    :class="tab === '{{ $s['type'] }}' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300'"
                                    class="shrink-0 inline-flex items-center gap-1.5 text-xs font-black border rounded-full px-3.5 py-2 transition-colors">
                                <i data-lucide="{{ $s['icon'] }}" class="w-3.5 h-3.5"></i>{{ $s['label'] }}
                                <span class="opacity-80">{{ $s['count'] }}</span>
                            </button>
                            @endif
                        @endforeach
                    </div>

                    {{-- 各種別の一覧 --}}
                    @foreach($sections as $s)
                        @if($s['count'] > 0)
                        <div x-show="tab === '{{ $s['type'] }}'" x-cloak class="space-y-2">
                            @foreach($s['entries'] as $e)
                            {{-- カード全体をクリッカブル（<a>入れ子を避けdivのAlpine @click＋role/tabindex/キーボードで遷移）。削除・見るは @click.stop で伝播分離。 --}}
                            <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-4 {{ $e['url'] ? 'cursor-pointer hover:border-blue-300 transition-colors' : '' }}"
                                 @if($e['url'])
                                 role="link" tabindex="0" aria-label="{{ $e['target'] }}の投稿を開く"
                                 @click="window.location.href = @js($e['url'])"
                                 @keydown.enter="window.location.href = @js($e['url'])"
                                 @keydown.space.prevent="window.location.href = @js($e['url'])"
                                 @endif>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="text-[10px] font-black text-gray-400 truncate {{ $e['url'] ? 'group-hover:underline' : '' }}">{{ $e['target'] }}</span>
                                            @unless($e['approved'])
                                            <span class="shrink-0 text-[9px] font-black text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">承認待ち</span>
                                            @endunless
                                        </div>
                                        <p class="text-sm text-gray-800 font-bold leading-snug break-words">{{ $e['snippet'] }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $e['created_at']?->format('Y/m/d') }}</p>
                                    </div>
                                    <div class="shrink-0 flex flex-col items-end gap-1.5">
                                        @if($e['url'])
                                        <a href="{{ $e['url'] }}" @click.stop class="inline-flex items-center gap-0.5 text-xs font-bold text-blue-600 hover:text-blue-700">見る<i data-lucide="external-link" class="w-3 h-3"></i></a>
                                        @endif
                                        <form method="POST" action="{{ route('mypage.contributions.destroy', ['type' => $e['type'], 'id' => $e['id']]) }}"
                                              @click.stop
                                              onsubmit="return confirm('この投稿を削除します。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" @click.stop class="inline-flex items-center gap-0.5 text-[11px] font-bold text-gray-400 hover:text-red-500 transition-colors"><i data-lucide="trash-2" class="w-3 h-3"></i>削除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-layout>
