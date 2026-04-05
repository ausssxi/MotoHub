<x-layout>
    <x-slot:title>バイクニュース | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイク業界の最新ニュースをチェック。新型モデル、モデルチェンジ、イベント情報など、バイクに関する話題をまとめてお届けします。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('news.index') }}</x-slot:canonical>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">ニュース</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-6">バイクニュース</h1>

        {{-- メーカーフィルタタブ --}}
        @if($manufacturers->isNotEmpty())
        <div class="mb-2">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('news.index') }}"
                   class="px-3 py-1.5 rounded-full text-xs font-bold transition {{ !$currentManufacturerId ? 'bg-black text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    すべて
                </a>
                @foreach($manufacturers as $mfr)
                <a href="{{ route('news.index', ['manufacturer_id' => $mfr->id]) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-bold transition {{ $currentManufacturerId == $mfr->id ? 'bg-black text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $mfr->name }}
                </a>
                @endforeach
            </div>
        </div>

        {{-- 車種サブタブ（メーカー選択時のみ表示） --}}
        @if($currentManufacturerId && $modelTabs->isNotEmpty())
        <div class="flex flex-wrap gap-1.5 mb-6 pl-2 border-l-2 border-gray-200">
            <a href="{{ route('news.index', ['manufacturer_id' => $currentManufacturerId]) }}"
               class="px-2.5 py-1 rounded-full text-[11px] font-bold transition {{ !$currentBikeModelId ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-600 hover:bg-blue-100' }}">
                全て
            </a>
            @foreach($modelTabs as $mt)
            <a href="{{ route('news.index', ['manufacturer_id' => $currentManufacturerId, 'bike_model_id' => $mt->id]) }}"
               class="px-2.5 py-1 rounded-full text-[11px] font-bold transition {{ $currentBikeModelId == $mt->id ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-600 hover:bg-blue-100' }}">
                {{ $mt->name }}
            </a>
            @endforeach
        </div>
        @else
        <div class="mb-6"></div>
        @endif
        @endif

        {{-- 注目のニュース（1ページ目 & engagement > 0 のみ） --}}
        @if($featured->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-3">注目のニュース</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($featured as $item)
                <a href="{{ route('news.show', $item->id) }}" class="block bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition group">
                    @if($item->thumbnail_url)
                    <div class="aspect-[16/9] bg-gray-100 overflow-hidden">
                        <img src="{{ $item->thumbnail_url }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy"
                             onerror="this.parentNode.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-50 to-blue-50 text-indigo-300\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'w-8 h-8\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'1.5\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5\'/></svg></div>'">
                    </div>
                    @else
                    <div class="aspect-[16/9] bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center text-indigo-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5"/></svg>
                    </div>
                    @endif
                    <div class="p-3">
                        <h3 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors">{{ $item->title }}</h3>
                        <div class="flex items-center gap-2 text-[10px] text-gray-400">
                            @if($item->source)<span class="font-bold text-gray-500">{{ $item->source }}</span>@endif
                            <span>{{ $item->published_at?->diffForHumans() }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            @if($item->comments_count > 0)
                            <span class="inline-flex items-center gap-0.5 text-[10px] font-bold text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                {{ $item->comments_count }}件
                            </span>
                            @endif
                            @if($item->picks_count > 0)
                            <span class="inline-flex items-center gap-0.5 text-[10px] font-bold text-yellow-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                {{ $item->picks_count }}
                            </span>
                            @endif
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ニュースリスト --}}
        <div class="space-y-1">
            @forelse($news as $article)
            <a href="{{ route('news.show', $article->id) }}" class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm sm:text-base font-bold text-gray-900 leading-snug mb-1.5 line-clamp-2 group-hover:text-blue-600 transition-colors">{{ $article->title }}</h2>
                    <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-gray-400">
                        @if($article->source)
                        <span class="font-bold text-gray-500">{{ $article->source }}</span>
                        @endif
                        <span>{{ $article->published_at?->diffForHumans() ?? '' }}</span>
                        @if($article->comments_count > 0)
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded-full font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            {{ $article->comments_count }}
                        </span>
                        @endif
                        @if($article->picks_count > 0)
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-yellow-50 text-yellow-600 rounded-full font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                            {{ $article->picks_count }}
                        </span>
                        @endif
                    </div>
                    {{-- 最新コメントプレビュー --}}
                    @if($article->comments->isNotEmpty())
                    @php $latestComment = $article->comments->first(); @endphp
                    <div class="mt-2 flex items-start gap-2 bg-gray-50 rounded-lg p-2">
                        <div class="w-5 h-5 rounded-full bg-gray-300 flex-shrink-0 overflow-hidden">
                            @if($latestComment->user->avatar)
                                <img src="{{ $latestComment->user->avatar }}" alt="" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <p class="text-[11px] text-gray-500 line-clamp-1 flex-1">{{ $latestComment->body }}</p>
                    </div>
                    @endif
                </div>
                {{-- サムネイル --}}
                @if($article->thumbnail_url)
                <div class="w-20 h-[56px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                    <img src="{{ $article->thumbnail_url }}" alt="" class="w-full h-full object-cover" loading="lazy"
                         onerror="this.style.display='none'">
                </div>
                @endif
            </a>
            @empty
            <div class="text-center py-16 text-gray-400">
                <p class="text-lg font-bold">ニュースがまだありません</p>
            </div>
            @endforelse
        </div>

        {{-- ページネーション --}}
        <div class="mt-8">
            {{ $news->links() }}
        </div>
    </div>
</x-layout>
