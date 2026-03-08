<x-layout>
    <x-slot:title>バイク駐車場を登録 | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイク駐車場の情報を共有しましょう。みんなの投稿でバイク駐車場マップを充実させよう。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #create-map { height: 350px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/parking/create.js') }}"></script>
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

                <form action="{{ route('parking.store') }}" method="POST" class="space-y-6">
                    @csrf

                    {{-- 駐車場名 --}}
                    <div>
                        <label for="name" class="block text-xs font-bold text-gray-700 mb-1">駐車場名 <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="100" placeholder="例: 東京駅八重洲バイク駐車場"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    {{-- 住所入力＋地図 --}}
                    <div>
                        <label for="address" class="block text-xs font-bold text-gray-700 mb-1">住所 <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" name="address" id="address" value="{{ old('address') }}" required maxlength="255" placeholder="例: 東京都中央区八重洲1-5"
                                class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            <button type="button" id="btn-geocode" class="bg-gray-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-gray-700 transition shrink-0">
                                住所検索
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">住所を入力して「住所検索」を押すか、地図上をクリックして位置を指定できます。</p>
                    </div>

                    <div id="create-map" class="w-full border border-gray-200"></div>

                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                    <input type="hidden" name="prefecture" id="prefecture" value="{{ old('prefecture') }}">
                    <input type="hidden" name="city" id="city" value="{{ old('city') }}">

                    {{-- 駐車場タイプ --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2">駐車場タイプ</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['bike_only' => 'バイク専用', 'car_shared' => '四輪と共用', 'bicycle_shared' => '自転車と共用', 'other' => 'その他'] as $value => $label)
                            <label class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm {{ old('parking_type', 'bike_only') === $value ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                                <input type="radio" name="parking_type" value="{{ $value }}" {{ old('parking_type', 'bike_only') === $value ? 'checked' : '' }}
                                    class="text-green-600 focus:ring-green-500">
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- 料金 --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2">料金情報</label>
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_free" value="1" {{ old('is_free') ? 'checked' : '' }}
                                    class="text-green-600 focus:ring-green-500 rounded" id="is_free_check">
                                無料駐車場
                            </label>
                            <div id="price-inputs" class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[10px] text-gray-500 font-bold">時間料金</label>
                                    <div class="flex items-center gap-1">
                                        <input type="number" name="price_per_hour" value="{{ old('price_per_hour') }}" min="0" placeholder="---"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <span class="text-xs text-gray-500 shrink-0">円</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] text-gray-500 font-bold">日額料金</label>
                                    <div class="flex items-center gap-1">
                                        <input type="number" name="price_per_day" value="{{ old('price_per_day') }}" min="0" placeholder="---"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <span class="text-xs text-gray-500 shrink-0">円</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] text-gray-500 font-bold">月額料金</label>
                                    <div class="flex items-center gap-1">
                                        <input type="number" name="price_per_month" value="{{ old('price_per_month') }}" min="0" placeholder="---"
                                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <span class="text-xs text-gray-500 shrink-0">円</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 設備 --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-2">設備</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                                <input type="checkbox" name="is_covered" value="1" {{ old('is_covered') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                                <i data-lucide="umbrella" class="w-4 h-4 text-gray-400"></i> 屋根あり
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                                <input type="checkbox" name="is_locked" value="1" {{ old('is_locked') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                                <i data-lucide="lock" class="w-4 h-4 text-gray-400"></i> 施錠可能
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                                <input type="checkbox" name="has_security_camera" value="1" {{ old('has_security_camera') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                                <i data-lucide="cctv" class="w-4 h-4 text-gray-400"></i> 防犯カメラ
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                                <input type="checkbox" name="available_24h" value="1" {{ old('available_24h') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                                <i data-lucide="clock" class="w-4 h-4 text-gray-400"></i> 24時間利用可
                            </label>
                        </div>
                    </div>

                    {{-- 収容台数 --}}
                    <div>
                        <label for="capacity" class="block text-xs font-bold text-gray-700 mb-1">収容台数（任意）</label>
                        <input type="number" name="capacity" id="capacity" value="{{ old('capacity') }}" min="1" placeholder="台数"
                            class="w-32 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    {{-- 補足説明 --}}
                    <div>
                        <label for="description" class="block text-xs font-bold text-gray-700 mb-1">補足説明（任意）</label>
                        <textarea name="description" id="description" rows="3" maxlength="1000" placeholder="利用時の注意点やアクセス方法など..."
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none">{{ old('description') }}</textarea>
                    </div>

                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition text-sm">
                        駐車場を登録する
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layout>
