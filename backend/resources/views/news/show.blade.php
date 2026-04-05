<x-layout>
    <x-slot:title>{{ $newsItem->title }} | バイクニュース | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ \Illuminate\Support\Str::limit($newsItem->title, 120) }}</x-slot:metaDescription>
    <x-slot:canonical>{{ route('news.show', $newsItem->id) }}</x-slot:canonical>
    @if($newsItem->thumbnail_url)
    <x-slot:ogImage>{{ $newsItem->thumbnail_url }}</x-slot:ogImage>
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
                <li class="text-gray-600 truncate max-w-[200px]">{{ $newsItem->title }}</li>
            </ol>
        </nav>

        {{-- ニュースヘッダー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-6">
            <h1 class="text-xl sm:text-2xl font-black text-gray-900 leading-tight mb-4">{{ $newsItem->title }}</h1>

            <div class="flex items-center gap-3 text-sm text-gray-500 mb-4">
                @if($newsItem->source)
                <span class="font-bold">{{ $newsItem->source }}</span>
                @endif
                <span>{{ $newsItem->published_at?->format('Y年m月d日 H:i') }}</span>
            </div>

            @if($newsItem->thumbnail_url)
            <div class="rounded-xl overflow-hidden bg-gray-100 mb-6 max-h-[400px]">
                <img src="{{ $newsItem->thumbnail_url }}" alt="{{ $newsItem->title }}" class="w-full h-full object-cover" loading="lazy"
                     onerror="this.parentNode.style.display='none'">
            </div>
            @endif

            <div class="flex items-center gap-3">
                {{-- 元記事リンク --}}
                <a href="{{ $newsItem->url }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-black text-white text-xs font-bold rounded-full hover:bg-gray-800 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    元記事を読む
                </a>

                {{-- ピックボタン --}}
                <div x-data="{ picked: {{ $isPicked ? 'true' : 'false' }}, count: {{ $newsItem->picks_count }}, loading: false }">
                    <button @click="if(!loading){ loading=true; fetch('{{ route('news.pick', $newsItem->id) }}', { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'} }).then(r=>r.json()).then(d=>{ picked=d.picked; count=d.picks_count; loading=false; }).catch(()=>{ loading=false; @guest window.location='{{ route('login') }}'; @endguest }) }"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-full transition border"
                            :class="picked ? 'bg-yellow-50 border-yellow-300 text-yellow-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="picked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        <span x-text="picked ? 'ピック済み' : 'ピック'"></span>
                        <span class="text-[10px] opacity-70" x-text="count"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- コメントセクション --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-6">
            <div class="flex items-center gap-2 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <h2 class="text-lg font-black text-gray-900">コメント <span class="text-sm text-gray-400 font-bold">({{ $comments->count() }})</span></h2>
            </div>

            {{-- コメント投稿フォーム --}}
            @auth
            <div x-data="{ body: '', submitting: false, error: '' }" class="mb-6">
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if(auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @endif
                    </div>
                    <div class="flex-1">
                        <textarea x-model="body" maxlength="500" rows="3" placeholder="コメントを書く..."
                                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition"></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-[10px] text-gray-400" x-text="body.length + '/500'"></span>
                            <button @click="if(body.trim() && !submitting){ submitting=true; error=''; fetch('{{ route('news.comment', $newsItem->id) }}', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}, body:JSON.stringify({body:body}) }).then(r=>{ if(!r.ok) throw r; return r.json(); }).then(d=>{ location.reload(); }).catch(()=>{ error='投稿に失敗しました'; submitting=false; }) }"
                                    :disabled="!body.trim() || submitting"
                                    class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-full hover:bg-blue-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-text="submitting ? '投稿中...' : '投稿'"></span>
                            </button>
                        </div>
                        <p x-show="error" x-text="error" class="text-xs text-red-500 mt-1"></p>
                    </div>
                </div>
            </div>
            @else
            <div class="mb-6 bg-gray-50 rounded-xl p-4 text-center">
                <p class="text-sm text-gray-500 mb-2">コメントするにはログインが必要です</p>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1 px-4 py-2 bg-black text-white text-xs font-bold rounded-full hover:bg-gray-800 transition">ログイン</a>
            </div>
            @endauth

            {{-- コメント一覧 --}}
            <div class="space-y-4">
                @forelse($comments as $comment)
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($comment->user->avatar)
                            <img src="{{ $comment->user->avatar }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-800">{{ $comment->user->name }}</span>
                            <span class="text-[10px] text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line break-words">{{ $comment->body }}</p>
                        <div class="mt-1.5" x-data="{ liked: {{ in_array($comment->id, $likedCommentIds) ? 'true' : 'false' }}, count: {{ $comment->likes_count }}, loading: false }">
                            <button @click="if(!loading){ loading=true; fetch('{{ route('news.comment.like', $comment->id) }}', { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'} }).then(r=>r.json()).then(d=>{ liked=d.liked; count=d.likes_count; loading=false; }).catch(()=>{ loading=false; @guest window.location='{{ route('login') }}'; @endguest }) }"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold transition"
                                    :class="liked ? 'text-red-500' : 'text-gray-400 hover:text-red-400'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span x-text="count"></span>
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-400 text-center py-4">まだコメントはありません</p>
                @endforelse
            </div>
        </div>

        {{-- 関連中古バイク --}}
        @if($relatedListings->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-6">
            <div class="flex items-center gap-2 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <h2 class="text-lg font-black text-gray-900">関連する中古バイク</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($relatedListings as $listing)
                <a href="{{ route('bikes.show', $listing->id) }}" class="block rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition">
                    <div class="aspect-[4/3] bg-gray-100">
                        @php
                            $img = is_string($listing->local_image_paths) ? json_decode($listing->local_image_paths, true) : $listing->local_image_paths;
                        @endphp
                        @if(!empty($img) && is_array($img))
                            <img src="{{ asset('storage/' . ltrim($img[0], '/')) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                        @endif
                    </div>
                    <div class="p-2">
                        <p class="text-xs font-bold text-gray-800 line-clamp-1">{{ $listing->title }}</p>
                        @if($listing->total_price)
                        <p class="text-xs font-black text-red-600">{{ number_format($listing->total_price) }}円</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 関連ニュース --}}
        @if($relatedNews->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
            <h2 class="text-lg font-black text-gray-900 mb-4">関連ニュース</h2>
            <div class="space-y-2">
                @foreach($relatedNews as $related)
                <a href="{{ route('news.show', $related->id) }}" class="flex items-start gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 line-clamp-2 mb-1">{{ $related->title }}</p>
                        <div class="flex items-center gap-2 text-[11px] text-gray-400">
                            @if($related->source)<span class="font-bold">{{ $related->source }}</span>@endif
                            <span>{{ $related->published_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                    @if($related->thumbnail_url)
                    <div class="w-16 h-[44px] rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                        <img src="{{ $related->thumbnail_url }}" alt="" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                    </div>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-layout>
