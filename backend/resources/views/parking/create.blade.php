<x-layout>
    <x-slot:title>バイク駐車場を登録 | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイク駐車場の情報を共有しましょう。みんなの投稿でバイク駐車場マップを充実させよう。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.breadcrumb-parking currentName="駐車場を登録" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #create-map { height: 350px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">駐車場を登録</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2 flex items-center gap-2">
                    <i data-lucide="square-parking" class="w-5 h-5 text-green-600"></i>
                    バイク駐車場を登録
                </h1>
                <p class="text-xs text-gray-500 mb-6">あなたが知っているバイク駐車場の情報を共有しましょう。</p>

                @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-6">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form action="{{ route('parking.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @include('parking._form')

                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition text-sm">
                        駐車場を登録する
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layout>
