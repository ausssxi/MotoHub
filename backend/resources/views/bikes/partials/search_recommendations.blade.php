{{-- おすすめ車種セクション（検索結果ページ用） --}}
@if(isset($recommendedModels) && $recommendedModels->isNotEmpty())
<div class="mt-12 bg-white rounded-3xl shadow-sm p-6 sm:p-8 border border-gray-100 animate-in fade-in duration-500">
    <h2 class="text-lg sm:text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
        <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg">
            <i data-lucide="sparkles" class="w-5 h-5"></i>
        </span>
        この車種が気になる人はこちらも
    </h2>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach($recommendedModels as $related)
        <a href="{{ $related->seo_url }}"
           class="group block bg-gray-50 rounded-xl p-4 border border-gray-100 hover:border-blue-300 hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
            <div class="aspect-[4/3] rounded-lg bg-gray-100 overflow-hidden mb-3">
                @if($related->image_url)
                    <img src="{{ $related->image_url }}"
                         alt="{{ $related->name }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                         loading="lazy" decoding="async">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                        <i data-lucide="bike" class="w-6 h-6"></i>
                    </div>
                @endif
            </div>
            <p class="text-[9px] font-bold text-gray-400 mb-0.5">{{ $related->manufacturer->name }}</p>
            <h3 class="text-xs font-black text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 mb-1">
                {{ $related->name }}
            </h3>
            @if($related->listings_count > 0)
            <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">
                {{ $related->listings_count }}台
            </span>
            @endif
        </a>
        @endforeach
    </div>
</div>
@endif
