<x-layout>
    {{-- ★公開表示は公開ハンドルのみ。user->name(本名)・メール・内部IDは一切出さない。 --}}
    <x-slot:title>{{ $handle }}のガレージ | ライダープロフィール | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $handle }}さんの公開ガレージ（{{ $garages->count() }}台）。愛車・燃費記録・整備ログを公開中。</x-slot:metaDescription>
    {{-- 暫定 noindex（本格版＝フォロー等が固まるまで半端な公開面を index させない） --}}
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">
        {{-- プロフィールヘッダー（公開ハンドルのみ） --}}
        <div class="bg-gradient-to-r from-pink-600 to-rose-500 text-white py-12 sm:py-16">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-3 backdrop-blur-sm">
                    <i data-lucide="user" class="w-8 h-8"></i>
                </div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-1">Rider Profile</p>
                <h1 class="text-2xl sm:text-3xl font-black mb-2">{{ $handle }}</h1>
                <p class="text-xs sm:text-sm text-white/80 font-medium mb-4">公開ガレージ {{ $garages->count() }} 台</p>

                {{-- フォロー（数は公開・一覧は非公開）。inline Alpine + fetch（@json不使用＝リテラル補間のみ） --}}
                <div x-data="{ following: {{ $isFollowing ? 'true' : 'false' }}, followers: {{ (int) $followersCount }}, loading: false }"
                     class="inline-flex items-center gap-4">
                    <span class="text-xs font-bold text-white/90">フォロワー <span class="font-black" x-text="followers">{{ (int) $followersCount }}</span></span>
                    <span class="text-xs font-bold text-white/90">フォロー中 <span class="font-black">{{ (int) $followingCount }}</span></span>

                    @auth
                        @unless($isSelf)
                            <button type="button"
                                    @click="if(!loading){ loading=true; fetch('{{ route('riders.follow', $token) }}', { method:'POST', headers:{ 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' } }).then(r => { if(r.status===401||r.status===419){ window.location='{{ route('login') }}'; return null; } return r.json(); }).then(d => { if(d){ following=d.following; followers=d.followers_count; } loading=false; }).catch(() => { loading=false; }) }"
                                    :class="following ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-white text-pink-600 hover:bg-white/90'"
                                    class="text-xs font-black px-5 py-2 rounded-full transition-colors"
                                    :aria-pressed="following">
                                <span x-text="following ? 'フォロー中' : '＋ フォロー'">＋ フォロー</span>
                            </button>
                        @endunless
                    @else
                        <a href="{{ route('login') }}" class="text-xs font-black px-5 py-2 rounded-full bg-white text-pink-600 hover:bg-white/90 transition-colors">＋ フォロー</a>
                    @endauth
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 py-10">
            {{-- パンくず --}}
            <nav class="mb-6 text-xs font-bold text-gray-400 flex items-center gap-1 flex-wrap">
                <a href="{{ route('bikes.index') }}" class="hover:text-gray-600 transition-colors">トップ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="{{ route('garage.public.index') }}" class="hover:text-gray-600 transition-colors">みんなのガレージ</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-gray-600">{{ $handle }}</span>
            </nav>

            <h2 class="text-lg font-black text-gray-900 mb-5 flex items-center gap-2">
                <i data-lucide="warehouse" class="w-5 h-5 text-pink-600"></i> {{ $handle }} のガレージ
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($garages as $g)
                    {{-- カード→公開ガレージ。いいねは別ボタン＝aの入れ子回避 --}}
                    <div class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:border-pink-200 transition-all duration-300">
                        <a href="{{ route('garage.public.show', $g['id']) }}" class="block">
                            <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
                                @if($g['image'])
                                    <img src="{{ $g['image'] }}" alt="{{ $g['bike_name'] }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                         loading="lazy" decoding="async">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <i data-lucide="bike" class="w-8 h-8"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="px-4 pt-4 pb-1">
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">{{ $g['manufacturer'] }}</p>
                                <h3 class="text-sm font-black text-gray-800 group-hover:text-pink-600 transition-colors line-clamp-1 mb-1">{{ $g['bike_name'] }}</h3>
                                <div class="flex items-center justify-between">
                                    <p class="text-[10px] font-bold text-gray-400">{{ number_format($g['odometer']) }} km</p>
                                    @if($g['model_year'])
                                        <span class="text-[10px] text-gray-400">{{ $g['model_year'] }}年式</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                        <div class="px-4 pb-3">
                            @include('mybikes._garage-like-button', ['bikeId' => $g['id'], 'likeCount' => $g['like_count'], 'liked' => in_array($g['id'], $likedGarageIds), 'isOwner' => $isOwnProfile])
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 投稿したレビュー（公開・承認済みのみ・表示名は現ハンドル） --}}
            @if($reviews->isNotEmpty())
            <h2 class="text-lg font-black text-gray-900 mt-12 mb-5 flex items-center gap-2">
                <i data-lucide="star" class="w-5 h-5 text-pink-600"></i> {{ $handle }} のレビュー
                <span class="text-sm text-gray-400 font-bold">({{ $reviews->count() }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($reviews as $review)
                    <a href="{{ $review->bikeModel->seo_url }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-pink-200 transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-xs font-bold text-gray-500">{{ $review->bikeModel->manufacturer->name ?? '' }} {{ $review->bikeModel->name ?? '' }}</p>
                            <span class="text-[11px] font-black text-amber-500 shrink-0 ml-2">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                        </div>
                        @if($review->title)<p class="text-sm font-black text-gray-800 line-clamp-1 mb-0.5">{{ $review->title }}</p>@endif
                        @if($review->body)<p class="text-xs text-gray-500 line-clamp-2">{{ $review->body }}</p>@endif
                        <p class="text-[10px] text-gray-400 font-bold mt-1.5">{{ $handle }} ・ {{ $review->created_at->format('Y/m/d') }}</p>
                    </a>
                @endforeach
            </div>
            @endif

            {{-- 駐車場レビュー（公開・表示設定ONのときのみ・display_name で本名を出さない） --}}
            @if($parkingReviews->isNotEmpty())
            <h2 class="text-lg font-black text-gray-900 mt-12 mb-5 flex items-center gap-2">
                <i data-lucide="square-parking" class="w-5 h-5 text-pink-600"></i> {{ $handle }} の駐車場レビュー
                <span class="text-sm text-gray-400 font-bold">({{ $parkingReviews->count() }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($parkingReviews as $pr)
                    <a href="{{ route('parking.show', $pr->bike_parking_id) }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-pink-200 transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-xs font-bold text-gray-500 line-clamp-1"><i data-lucide="map-pin" class="w-3 h-3 inline"></i> {{ $pr->bikeParking->name ?? '駐車場' }}</p>
                            <span class="text-[11px] font-black text-amber-500 shrink-0 ml-2">{{ str_repeat('★', (int) $pr->rating) }}{{ str_repeat('☆', 5 - (int) $pr->rating) }}</span>
                        </div>
                        @if($pr->body)<p class="text-xs text-gray-500 line-clamp-2">{{ $pr->body }}</p>@endif
                        <p class="text-[10px] text-gray-400 font-bold mt-1.5">{{ $pr->display_name }} ・ {{ $pr->created_at->format('Y/m/d') }}</p>
                    </a>
                @endforeach
            </div>
            @endif

            {{-- ショップレビュー（店ページで公開済みコメントのみ・comment_approved・店ページへリンク） --}}
            @if($shopReviews->isNotEmpty())
            <h2 class="text-lg font-black text-gray-900 mt-12 mb-5 flex items-center gap-2">
                <i data-lucide="store" class="w-5 h-5 text-pink-600"></i> {{ $handle }} のショップレビュー
                <span class="text-sm text-gray-400 font-bold">({{ $shopReviews->count() }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($shopReviews as $sr)
                    <a href="{{ route('shops.show', $sr->shop_id) }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-pink-200 transition-all">
                        <p class="text-xs font-bold text-gray-500 line-clamp-1 mb-1"><i data-lucide="store" class="w-3 h-3 inline"></i> {{ $sr->shop->name ?? 'ショップ' }}</p>
                        <p class="text-sm text-gray-700 line-clamp-3 break-words">{{ $sr->comment }}</p>
                        <p class="text-[10px] text-gray-400 font-bold mt-1.5">{{ $handle }} ・ {{ $sr->created_at->format('Y/m/d') }}</p>
                    </a>
                @endforeach
            </div>
            @endif

            {{-- ニュースコメント（公開・ページング） --}}
            @if($comments->isNotEmpty())
            <h2 class="text-lg font-black text-gray-900 mt-12 mb-5 flex items-center gap-2">
                <i data-lucide="message-circle" class="w-5 h-5 text-pink-600"></i> {{ $handle }} のニュースコメント
                <span class="text-sm text-gray-400 font-bold">({{ number_format($comments->total()) }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($comments as $comment)
                    <a href="{{ route('news.show', $comment->news_id) }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-pink-200 transition-all">
                        @if($comment->news)<p class="text-[11px] font-bold text-pink-600 line-clamp-1 mb-1"><i data-lucide="newspaper" class="w-3 h-3 inline"></i> {{ $comment->news->title }}</p>@endif
                        <p class="text-sm text-gray-700 line-clamp-3 break-words">{{ $comment->body }}</p>
                        <p class="text-[10px] text-gray-400 font-bold mt-1.5">{{ $handle }} ・ {{ $comment->created_at->format('Y/m/d') }}</p>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">
                {{ $comments->links() }}
            </div>
            @endif

            {{-- 執筆記事（TouringGuide・published のみ・著者=writer のときだけ非空） --}}
            @if($guides->isNotEmpty())
            <h2 class="text-lg font-black text-gray-900 mt-12 mb-5 flex items-center gap-2">
                <i data-lucide="pen-line" class="w-5 h-5 text-pink-600"></i> {{ $handle }} の執筆記事
                <span class="text-sm text-gray-400 font-bold">({{ $guides->count() }})</span>
            </h2>
            <div class="space-y-3">
                @foreach($guides as $guide)
                    <a href="{{ route('touring.show', $guide->slug) }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-pink-200 transition-all">
                        <p class="text-sm font-black text-gray-800 line-clamp-1 mb-0.5">{{ $guide->title }}</p>
                        @if($guide->excerpt)<p class="text-xs text-gray-500 line-clamp-2">{{ $guide->excerpt }}</p>@endif
                        <p class="text-[10px] text-gray-400 font-bold mt-1.5">@if($guide->prefecture){{ $guide->prefecture }} ・ @endif{{ optional($guide->published_at)->format('Y/m/d') }}</p>
                    </a>
                @endforeach
            </div>
            @endif

            {{-- 登録CTA（未ログインのみ・Xからの着地→登録導線） --}}
            <div class="mt-12">
                @include('mybikes._register-cta')
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
    </x-slot:scripts>
</x-layout>
