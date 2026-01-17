<x-layout>
    <x-slot:title>車種一覧から探す - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-7xl mx-auto px-4">
            
            {{-- ヘッダー部分 --}}
            <div class="mb-12 border-b border-gray-200 pb-8">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-4 tracking-tighter uppercase">
                    車種一覧
                </h1>
                <p class="text-gray-400 text-sm font-bold tracking-widest uppercase">
                    {{ number_format($totalModelsCount) }} 車種
                </p>
            </div>

            {{-- メーカー別車種リスト --}}
            <div class="space-y-16">
                @foreach($manufacturers as $manufacturer)
                    @if($manufacturer->bike_models_count > 0)
                    <section id="maker-{{ $manufacturer->id }}" class="scroll-mt-24">
                        <div class="flex items-center gap-4 mb-6">
                            <h2 class="text-xl sm:text-2xl font-black text-black tracking-tight">{{ $manufacturer->name }}</h2>
                            <span class="text-[10px] font-black bg-gray-900 text-white px-2 py-0.5 rounded tracking-tighter uppercase">
                                {{ $manufacturer->bike_models_count }} モデル
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                            @foreach($manufacturer->bikeModels as $bike)
                            <a href="{{ route('bikes.search', ['keyword' => $bike->name]) }}"
                                class="group flex items-center bg-white p-2 sm:p-3 rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition-all duration-200">
                                
                                {{-- 車種画像 --}}
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg bg-gray-50 overflow-hidden flex-shrink-0 mr-3 border border-gray-50">
                                    @if($bike->image_url)
                                    <img src="{{ $bike->image_url }}" alt="{{ $bike->name }}" 
                                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="hidden w-full h-full items-center justify-center text-gray-300">
                                        <i data-lucide="bike" class="w-4 h-4"></i>
                                    </div>
                                    @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 bg-gray-100">
                                        <i data-lucide="bike" class="w-4 h-4 text-gray-300"></i>
                                    </div>
                                    @endif
                                </div>

                                {{-- 車種名と台数 --}}
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-[11px] sm:text-xs font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors leading-tight">
                                        {{ $bike->name }}
                                    </h3>
                                    <p class="text-[9px] text-gray-400 mt-0.5">
                                        <span class="text-blue-500 font-black">{{ number_format($bike->listings_count ?? 0) }}</span>台
                                    </p>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </section>
                    @endif
                @endforeach
            </div>

            {{-- ページ下部バックリンク --}}
            <div class="mt-24 pt-12 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-[0.2em]">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> トップページに戻る
                </a>
            </div>
        </div>
    </div>
</x-layout>