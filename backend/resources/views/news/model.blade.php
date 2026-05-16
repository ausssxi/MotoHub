<x-layout>
    <x-slot:title>{{ $bikeModel->name }}のニュース | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $bikeModel->name }}に関する最新ニュース一覧。新型情報、モデルチェンジ、レビューなどをまとめてチェック。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('news.model', $bikeModel->id) }}</x-slot:canonical>
    @if($news->isEmpty())
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li><a href="{{ route('news.index') }}" class="hover:text-black transition">ニュース</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">{{ $bikeModel->name }}</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2">{{ $bikeModel->name }}のニュース</h1>
        @if($bikeModel->manufacturer)
        <p class="text-sm text-gray-500 mb-6">{{ $bikeModel->manufacturer->name }}</p>
        @endif

        {{-- ニュースリスト --}}
        <div class="space-y-1">
            @forelse($news as $article)
            <a href="{{ route('news.show', $article->id) }}" class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm sm:text-base font-bold text-gray-900 leading-snug mb-1.5 line-clamp-2 group-hover:text-blue-600 transition-colors">{{ $article->title }}</h2>
                    <div class="flex items-center gap-2 text-[11px] text-gray-400">
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
                @if($article->thumbnail_url)
                <div class="w-20 h-[56px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                    <img src="{{ $article->thumbnail_url }}" alt="" class="w-full h-full object-cover" loading="lazy"
                         onerror="this.style.display='none'">
                </div>
                @endif
            </a>
            @empty
            <div class="text-center py-16 text-gray-400">
                <p class="text-lg font-bold">この車種のニュースはまだありません</p>
            </div>
            @endforelse
        </div>

        {{-- ページネーション --}}
        <div class="mt-8">
            {{ $news->links() }}
        </div>

        {{-- 車種ページへのリンク --}}
        <div class="mt-6 text-center">
            <a href="{{ url($bikeModel->seo_url) }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-black text-white text-xs font-bold rounded-full hover:bg-gray-800 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                {{ $bikeModel->name }}の中古バイクを見る
            </a>
        </div>
    </div>
</x-layout>
