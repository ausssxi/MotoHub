{{-- 
  PV爆上げ用：レコメンドセクション
  show.blade.php のメインカラムとサイドバーの下に配置されます。
--}}
<div class="pt-12 mt-12 border-t border-gray-200 space-y-16">

    {{-- 1. この車種の他の車両（本命比較） --}}
    @if(!empty($relatedListings) && count($relatedListings) > 0)
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">この車種の他の車両</h3>
            </div>
            <a href="{{ route('bikes.search', ['bike_model_id' => $listing->bike_model_id]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                すべて見る <i data-lucide="chevron-right" class="w-4 h-4 inline-block align-text-bottom"></i>
            </a>
        </div>
        
        <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($relatedListings as $related)
            <a href="{{ route('bikes.show', $related['id']) }}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block relative">
                <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                    @if(!empty($related['images']) && isset($related['images'][0]))
                        <img src="{{ $related['images'][0] }}" 
                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                    @else
                        <div class="flex items-center justify-center h-full text-gray-300">
                            <i data-lucide="image-off" class="w-8 h-8"></i>
                        </div>
                    @endif
                    <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                        {{ $related['total_price'] }}万円
                    </div>
                </div>
                <div class="p-3">
                    <div class="text-[10px] font-bold text-gray-400 mb-0.5 flex items-center gap-1">
                        <span class="bg-gray-100 px-1.5 rounded">{{ $related['model_year'] }}</span>
                        <span>{{ $related['mileage'] }}</span>
                    </div>
                    <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 h-[2.5em] group-hover:text-blue-600 transition-colors">
                        {{ $related['name'] }}
                    </h4>
                    <div class="flex items-end justify-between border-t border-gray-100 pt-2">
                        <div class="text-[10px] text-gray-400 truncate w-full">{{ $related['prefecture'] }}</div>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- 2. 類似車両（関連バイク・視野の拡大） --}}
    @if(!empty($similarListings) && count($similarListings) > 0)
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">似ている条件の車両</h3>
            </div>
            <a href="{{ route('bikes.search', ['manufacturer_id' => $listing->manufacturer_id]) }}" class="text-xs font-bold text-purple-600 hover:text-purple-800 transition-colors">
                同じメーカーを探す <i data-lucide="chevron-right" class="w-4 h-4 inline-block align-text-bottom"></i>
            </a>
        </div>
        
        <div class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            @foreach($similarListings as $similar)
            <a href="{{ route('bikes.show', $similar['id']) }}" class="snap-start shrink-0 w-40 sm:w-48 bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all group block relative">
                <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden">
                    @if(!empty($similar['images']) && isset($similar['images'][0]))
                        <img src="{{ $similar['images'][0] }}" 
                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1558981403-c5f9899a28bc?q=80&w=600&auto=format&fit=crop'; this.classList.add('grayscale', 'opacity-50');"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                    @else
                        <div class="flex items-center justify-center h-full text-gray-300">
                            <i data-lucide="image-off" class="w-8 h-8"></i>
                        </div>
                    @endif
                    <div class="absolute bottom-2 right-2 bg-black/60 backdrop-blur-sm text-white px-2 py-0.5 rounded text-[10px] font-black">
                        {{ $similar['total_price'] }}万円
                    </div>
                </div>
                <div class="p-3">
                    <div class="text-[10px] font-bold text-gray-400 mb-0.5 flex items-center gap-1">
                        <span class="bg-purple-50 text-purple-600 px-1.5 rounded">{{ $similar['maker'] }}</span>
                    </div>
                    <h4 class="text-xs font-black text-gray-800 leading-tight line-clamp-2 mb-2 h-[2.5em] group-hover:text-purple-600 transition-colors">
                        {{ $similar['name'] }}
                    </h4>
                    <div class="flex items-end justify-between border-t border-gray-100 pt-2">
                        <div class="text-[10px] text-gray-400 truncate w-full">{{ $similar['prefecture'] }}</div>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- 3. 最近見た車両（JSで取得） --}}
    <section id="history-section" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-gray-50 rounded-lg text-gray-600">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">最近見た車両</h3>
            </div>
        </div>
        <div id="history-widget" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            {{-- JSでカードが挿入されます --}}
        </div>
    </section>

    {{-- ★PV・SEO爆上げ：関連する条件で探す（掛け合わせリンク） --}}
    <section>
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-green-50 rounded-lg text-green-600">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900">関連する条件で探す</h3>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-2 sm:gap-3">
            @php
                $dynamicLinks = [];
                
                // ① コントローラーから渡された基本的なSEOリンク
                if(!empty($seoLinks) && is_iterable($seoLinks)) {
                    foreach($seoLinks as $link) {
                        $dynamicLinks[] = [
                            'icon' => 'chevron-right',
                            'label' => $link['label'] ?? '関連車両',
                            'url' => $link['url'] ?? '#'
                        ];
                    }
                }

                // ② さらに詳細な「掛け合わせリンク」を動的生成
                $maker = $listing->maker ?? '';
                $pref = $listing->prefecture ?? '';
                $cat = $listing->category ?? '';
                
                if ($maker && $pref) {
                    $dynamicLinks[] = [
                        'icon' => 'map-pin',
                        'label' => "{$pref} × {$maker} のバイク",
                        'url' => route('bikes.search', ['prefecture' => $pref, 'keyword' => $maker])
                    ];
                }
                if ($cat && $pref) {
                    $dynamicLinks[] = [
                        'icon' => 'map-pin',
                        'label' => "{$pref} × {$cat}",
                        'url' => route('bikes.search', ['prefecture' => $pref, 'keyword' => $cat])
                    ];
                }
                
                // ③ タグとの掛け合わせ（超強力なロングテールSEO）
                if (isset($tags) && is_iterable($tags)) {
                    $count = 0;
                    foreach($tags as $tag) {
                        if ($count >= 6) break; // 多すぎるとUIが崩れるので最大6つまで
                        
                        $tagName = $tag->name ?? '';
                        $tagSlug = $tag->slug ?? '';
                        
                        if (!$tagName) continue;

                        $dynamicLinks[] = [
                            'icon' => 'hash',
                            'label' => "{$tagName} のバイク",
                            'url' => route('bikes.search', ['tag' => $tagSlug])
                        ];

                        if ($cat) {
                            $dynamicLinks[] = [
                                'icon' => 'hash',
                                'label' => "{$tagName} × {$cat}",
                                'url' => route('bikes.search', ['tag' => $tagSlug, 'keyword' => $cat])
                            ];
                        }
                        if ($maker) {
                            $dynamicLinks[] = [
                                'icon' => 'hash',
                                'label' => "{$tagName} × {$maker}",
                                'url' => route('bikes.search', ['tag' => $tagSlug, 'keyword' => $maker])
                            ];
                        }
                        $count++;
                    }
                }
                
                // 重複を排除 (同じURLのリンクを消す)
                $uniqueLinks = collect($dynamicLinks)->unique('url')->values()->all();
            @endphp

            {{-- リンクの描画 --}}
            @foreach($uniqueLinks as $link)
                <a href="{{ $link['url'] }}" class="inline-flex items-center bg-white border border-gray-200 text-gray-600 hover:text-blue-600 hover:border-blue-400 hover:bg-blue-50 hover:shadow-md px-4 py-2 sm:py-2.5 rounded-xl text-xs font-bold transition-all group">
                    <i data-lucide="{{ $link['icon'] ?? 'search' }}" class="w-3.5 h-3.5 inline-block mr-1.5 text-gray-400 group-hover:text-blue-500 transition-colors"></i>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </section>
</div>