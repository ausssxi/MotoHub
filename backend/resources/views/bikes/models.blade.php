<x-layout>
    <x-slot:title>車種一覧から探す - MotoHub</x-slot:title>

    <x-slot:scripts>
        <script src="{{ asset('js/bikes/models.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-4xl mx-auto px-4">
            
            {{-- ヘッダー部分 --}}
            <div class="mb-10 text-center">
                <h1 class="text-2xl sm:text-3xl font-black text-black mb-2 tracking-tighter">
                    車種から探す
                </h1>
                <p class="text-gray-400 text-xs font-bold tracking-widest uppercase">
                    {{ number_format($totalModelsCount) }} MODELS AVAILABLE
                </p>
            </div>

            {{-- メーカー別アコーディオンリスト --}}
            <div class="space-y-4">
                {{-- 
                    Serviceで整形済みのため、配列としてアクセスします
                    $manufacturer は Eloquentモデルではなく配列になっています
                --}}
                @foreach($manufacturers as $manufacturer)
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-300 hover:shadow-md" id="maker-section-{{ $manufacturer['id'] }}">
                        
                        {{-- アコーディオンヘッダー --}}
                        <button onclick="toggleMaker({{ $manufacturer['id'] }})" class="w-full flex items-center justify-between px-4 sm:px-6 py-4 bg-white hover:bg-gray-50/50 transition-colors text-left group cursor-pointer focus:outline-none">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center group-hover:border-blue-200 transition-colors">
                                    @if($manufacturer['image_url'])
                                        <img src="{{ $manufacturer['image_url'] }}" alt="{{ $manufacturer['name'] }}" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                                    @else
                                        <i data-lucide="bike" class="w-5 h-5 text-gray-300"></i>
                                    @endif
                                </div>

                                <div class="flex flex-col">
                                    <h2 class="text-lg sm:text-xl font-black text-gray-800 tracking-tight group-hover:text-blue-600 transition-colors leading-none mb-1">
                                        {{ $manufacturer['name'] }}
                                    </h2>
                                    <span class="text-[10px] font-bold text-gray-400">
                                        {{ $manufacturer['bike_models_count'] }} Models
                                    </span>
                                </div>
                            </div>
                            
                            <div class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition-all duration-300 transform" id="maker-icon-{{ $manufacturer['id'] }}">
                                <i data-lucide="chevron-down" class="w-5 h-5"></i>
                            </div>
                        </button>

                        {{-- メーカー詳細エリア --}}
                        <div id="maker-list-{{ $manufacturer['id'] }}" class="hidden border-t border-gray-100 bg-gray-50/30">
                            <div class="p-2 sm:p-4 space-y-2">
                                
                                {{-- 整形済みのグループデータをループ --}}
                                @foreach($manufacturer['groups'] as $label => $list)
                                    @if(count($list) > 0)
                                        @php $subId = $manufacturer['id'] . '-' . $loop->index; @endphp
                                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                            <button onclick="toggleSubGroup('{{ $subId }}')" class="w-full flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left group/sub">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-sm font-bold text-gray-700 min-w-[2rem]">{{ $label }}</span>
                                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ count($list) }}</span>
                                                </div>
                                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-300 group-hover/sub:text-blue-500 transition-transform duration-200" id="sub-icon-{{ $subId }}"></i>
                                            </button>

                                            <div id="sub-list-{{ $subId }}" class="hidden border-t border-gray-100 p-3 sm:p-4 bg-gray-50/50">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                                    @foreach($list as $bike)
                                                    <a href="{{ route('bikes.search', ['bike_model_id' => $bike->id]) }}"
                                                        class="group/item flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                                                        
                                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-100 relative">
                                                            @if($bike->image_url)
                                                            <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}" 
                                                                 class="w-full h-full object-cover transform group-hover/item:scale-110 transition-transform duration-500"
                                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                            <div class="hidden w-full h-full items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @else
                                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                                <i data-lucide="bike" class="w-5 h-5"></i>
                                                            </div>
                                                            @endif
                                                        </div>

                                                        <div class="flex-1 min-w-0">
                                                            <h3 class="text-xs font-bold text-gray-700 leading-tight group-hover/item:text-blue-600 transition-colors line-clamp-1 mb-1">
                                                                {{ $bike->name }}
                                                            </h3>
                                                            <span class="inline-flex items-center text-[9px] font-medium text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                                                <span class="text-blue-500 font-bold mr-0.5">{{ number_format($bike->listings_count ?? 0) }}</span>台
                                                            </span>
                                                        </div>
                                                    </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ページ下部バックリンク --}}
            <div class="mt-16 pt-8 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> トップページに戻る
                </a>
            </div>
        </div>
    </div>
</x-layout>