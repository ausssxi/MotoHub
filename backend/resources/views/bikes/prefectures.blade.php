<x-layout>
    <x-slot:title>地域から探す - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12 sm:py-20">
        <div class="max-w-5xl mx-auto px-4">
            
            {{-- ヘッダー --}}
            <div class="text-center mb-16">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-3 tracking-tighter">
                    地域から探す
                </h1>
                <p class="text-gray-400 text-xs font-bold tracking-widest uppercase">
                    Search by Area
                </p>
            </div>

            {{-- エリア別リスト --}}
            <div class="space-y-12">
                @if(!empty($regions))
                    @foreach($regions as $regionName => $prefs)
                        <section class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-10">
                            <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                                <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>
                                {{ $regionName }}
                            </h2>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                @foreach($prefs as $pref)
                                    <a href="{{ route('bikes.search', ['prefecture' => $pref]) }}" 
                                       class="group flex items-center justify-between px-4 py-3 rounded-xl bg-white border border-gray-200 shadow-sm hover:border-blue-500 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                                        <span class="text-sm font-black text-gray-700 group-hover:text-blue-600 transition-colors">
                                            {{ $pref }}
                                        </span>
                                        <div class="w-6 h-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-400 group-hover:text-blue-500 transition-colors"></i>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @else
                    <div class="text-center text-gray-400 font-bold py-10">
                        地域データの読み込みに失敗しました。
                    </div>
                @endif
            </div>

            {{-- 戻るリンク --}}
            <div class="mt-20 pt-10 border-t border-gray-200 text-center">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> トップページに戻る
                </a>
            </div>

        </div>
    </div>
</x-layout>