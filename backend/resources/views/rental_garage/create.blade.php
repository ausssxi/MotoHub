<x-layout>
    <x-slot:title>レンタルガレージ・バイク保管場所を登録 | MotoHub</x-slot:title>
    <x-slot:metaDescription>レンタルガレージやバイク保管場所の情報を共有しましょう。みんなの投稿でバイク保管マップを充実させよう。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #create-map { height: 350px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        {{-- 地図ピン＋住所ジオコーディングは駐輪場と同一（画面構造を揃える）。画像/料金部は null-guard 済で無害。 --}}
        <script src="{{ asset('js/parking/create.js') }}?v={{ asset_buster(public_path('js/parking/create.js')) }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('riders.map') }}" class="hover:text-gray-600 transition-colors">ライダーズマップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">レンタルガレージを登録</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="warehouse" class="w-5 h-5 text-violet-600"></i>
                    レンタルガレージを登録
                </h1>
                <p class="text-xs text-gray-500 mb-6">あなたが知っているレンタルガレージ・バイク保管場所を共有しましょう。</p>

                @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-6">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    {{-- 重複時の既存レコードへの導線 --}}
                    @if(session('duplicate'))
                    <div class="mt-2 pt-2 border-t border-red-200 text-xs">
                        既存: <strong>{{ session('duplicate')['name'] }}</strong>（{{ session('duplicate')['address'] }}）
                        <a href="{{ session('duplicate')['url'] }}" class="underline font-bold ml-1">既存の詳細を見る</a>
                    </div>
                    @endif
                </div>
                @endif

                <form action="{{ route('rental-garage.store') }}" method="POST" class="space-y-6">
                    @csrf
                    @include('rental_garage._form')

                    <button type="submit" class="w-full bg-violet-600 text-white font-bold py-3 rounded-xl hover:bg-violet-700 transition text-sm">
                        レンタルガレージを登録する
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layout>
