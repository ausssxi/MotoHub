@props(['crossLinks'])

@if(!empty($crossLinks))
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-8">
    <div class="flex items-center gap-2 mb-4 sm:mb-6">
        <div class="p-1.5 sm:p-2 bg-gray-100 rounded-lg text-gray-600">
            <i data-lucide="compass" class="w-4 h-4 sm:w-5 sm:h-5"></i>
        </div>
        <h3 class="text-base sm:text-lg font-black text-gray-900">もっと探す</h3>
    </div>

    <div class="grid grid-cols-3 sm:grid-cols-3 gap-2 sm:gap-3">
        @foreach($crossLinks as $link)
        <a href="{{ $link['url'] }}" class="group flex flex-col items-center gap-1.5 sm:flex-row sm:items-start sm:gap-3 bg-gray-50 hover:bg-blue-50 border border-gray-100 hover:border-blue-200 rounded-xl p-2.5 sm:p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm text-center sm:text-left">
            <div class="shrink-0 w-8 h-8 rounded-lg bg-white border border-gray-100 group-hover:border-blue-200 flex items-center justify-center text-gray-400 group-hover:text-blue-600 transition-colors">
                <i data-lucide="{{ $link['icon'] ?? 'arrow-right' }}" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] sm:text-xs font-black text-gray-800 group-hover:text-blue-700 transition-colors line-clamp-2">{{ $link['label'] }}</p>
                @if(!empty($link['description']))
                <p class="text-[10px] text-gray-400 mt-0.5 line-clamp-1 hidden sm:block">{{ $link['description'] }}</p>
                @endif
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif
