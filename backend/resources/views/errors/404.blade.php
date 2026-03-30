<x-layout>
    <x-slot:title>{{ $message ?? 'ページが見つかりません' }} | MotoHub</x-slot:title>
    <x-slot:robotsMeta>noindex, nofollow</x-slot:robotsMeta>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="text-center max-w-md">
            <h1 class="text-xl font-bold text-gray-800 mb-2">
                {{ $message ?? 'ページが見つかりません' }}
            </h1>

            @if(!empty($bikeName))
                <p class="text-gray-500 mb-8">
                    「{{ $bikeName }}」の掲載は終了しましたが、類似の車両が見つかるかもしれません。
                </p>
            @else
                <p class="text-gray-500 mb-8">
                    お探しのページは移動または削除された可能性があります。
                </p>
            @endif

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                @if(!empty($searchUrl))
                    <a href="{{ $searchUrl }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        類似車両を探す
                    </a>
                @endif
                <a href="{{ route('bikes.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/>
                    </svg>
                    TOPページへ
                </a>
            </div>
        </div>
    </div>
</x-layout>
