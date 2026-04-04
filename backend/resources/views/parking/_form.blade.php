{{-- 駐車場 登録/編集 共通フォーム --}}
@php
    $isEdit = isset($parking);
    $val = fn(string $field) => old($field, $isEdit ? $parking->$field : null);
    $checked = fn(string $field) => old($field, $isEdit ? $parking->$field : false);
@endphp

<div class="space-y-6">

    {{-- 駐車場名 --}}
    <div>
        <label for="name" class="block text-xs font-bold text-gray-700 mb-1">駐車場名 <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ $val('name') }}" required maxlength="100" placeholder="例: 東京駅八重洲バイク駐車場"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
    </div>

    {{-- 住所入力＋地図 --}}
    <div>
        <label for="address" class="block text-xs font-bold text-gray-700 mb-1">住所 <span class="text-red-500">*</span></label>
        <div class="flex gap-2">
            <input type="text" name="address" id="address" value="{{ $val('address') }}" required maxlength="255" placeholder="例: 東京都中央区八重洲1-5"
                class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
            <button type="button" id="btn-geocode" class="bg-gray-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-gray-700 transition shrink-0">
                住所検索
            </button>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">住所を入力して「住所検索」を押すか、地図上をクリックして位置を指定できます。</p>
    </div>

    <div id="create-map" class="w-full border border-gray-200"></div>

    <input type="hidden" name="latitude" id="latitude" value="{{ $val('latitude') }}">
    <input type="hidden" name="longitude" id="longitude" value="{{ $val('longitude') }}">
    <input type="hidden" name="prefecture" id="prefecture" value="{{ $val('prefecture') }}">
    <input type="hidden" name="city" id="city" value="{{ $val('city') }}">

    {{-- 駐車場タイプ --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-2">駐車場タイプ</label>
        <div class="grid grid-cols-2 gap-2">
            @foreach(['bike_only' => 'バイク専用', 'car_shared' => '四輪と共用', 'bicycle_shared' => '自転車と共用', 'other' => 'その他'] as $value => $label)
            <label class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm {{ old('parking_type', $isEdit ? $parking->parking_type : 'bike_only') === $value ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                <input type="radio" name="parking_type" value="{{ $value }}" {{ old('parking_type', $isEdit ? $parking->parking_type : 'bike_only') === $value ? 'checked' : '' }}
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
                <input type="checkbox" name="is_free" value="1" {{ $checked('is_free') ? 'checked' : '' }}
                    class="text-green-600 focus:ring-green-500 rounded" id="is_free_check">
                無料駐車場
            </label>
            <div id="price-inputs" class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[10px] text-gray-500 font-bold">時間料金</label>
                    <div class="flex items-center gap-1">
                        <input type="text" inputmode="numeric" name="price_per_hour" value="{{ $val('price_per_hour') }}" placeholder="例: 100"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition price-numeric">
                        <span class="text-xs text-gray-500 shrink-0">円</span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] text-gray-500 font-bold">日額料金</label>
                    <div class="flex items-center gap-1">
                        <input type="text" inputmode="numeric" name="price_per_day" value="{{ $val('price_per_day') }}" placeholder="例: 800"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition price-numeric">
                        <span class="text-xs text-gray-500 shrink-0">円</span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] text-gray-500 font-bold">月額料金</label>
                    <div class="flex items-center gap-1">
                        <input type="text" inputmode="numeric" name="price_per_month" value="{{ $val('price_per_month') }}" placeholder="例: 5000"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition price-numeric">
                        <span class="text-xs text-gray-500 shrink-0">円</span>
                    </div>
                </div>
            </div>

            {{-- 料金の補足 --}}
            <div>
                <label for="price_detail" class="text-[10px] text-gray-500 font-bold">料金の補足（任意）</label>
                <textarea name="price_detail" id="price_detail" rows="2" maxlength="500" placeholder="例: 6時間100円、最大料金800円/日、夜間割引あり"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none mt-1">{{ $val('price_detail') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 設備 --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-2">設備</label>
        <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                <input type="checkbox" name="is_covered" value="1" {{ $checked('is_covered') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                <i data-lucide="umbrella" class="w-4 h-4 text-gray-400"></i> 屋根あり
            </label>
            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                <input type="checkbox" name="is_locked" value="1" {{ $checked('is_locked') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                <i data-lucide="lock" class="w-4 h-4 text-gray-400"></i> 施錠可能
            </label>
            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                <input type="checkbox" name="has_security_camera" value="1" {{ $checked('has_security_camera') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                <i data-lucide="cctv" class="w-4 h-4 text-gray-400"></i> 防犯カメラ
            </label>
            <label class="flex items-center gap-2 px-3 py-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm">
                <input type="checkbox" name="available_24h" value="1" {{ $checked('available_24h') ? 'checked' : '' }} class="text-green-600 focus:ring-green-500 rounded">
                <i data-lucide="clock" class="w-4 h-4 text-gray-400"></i> 24時間利用可
            </label>
        </div>
    </div>

    {{-- 収容台数 --}}
    <div>
        <label for="capacity" class="block text-xs font-bold text-gray-700 mb-1">収容台数（任意）</label>
        <input type="number" name="capacity" id="capacity" value="{{ $val('capacity') }}" min="1" placeholder="台数"
            class="w-32 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
    </div>

    {{-- 補足説明 --}}
    <div>
        <label for="description" class="block text-xs font-bold text-gray-700 mb-1">補足説明（任意）</label>
        <textarea name="description" id="description" rows="3" maxlength="1000" placeholder="利用時の注意点やアクセス方法など..."
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none">{{ $val('description') }}</textarea>
    </div>

    {{-- 画像アップロード --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-1">
            写真（任意）
            <span id="image-count" class="text-gray-400 font-normal ml-1">{{ $isEdit ? $parking->images->count() : 0 }}/5</span>
        </label>

        {{-- 既存画像プレビュー（編集時） --}}
        @if($isEdit && $parking->images->isNotEmpty())
        <div id="existing-images" class="flex gap-2 mb-3 overflow-x-auto pb-1">
            @foreach($parking->images as $img)
            <div class="relative group w-24 h-24 rounded-xl overflow-hidden border border-gray-200 shrink-0" data-image-id="{{ $img->id }}">
                <img src="{{ asset('storage/' . $img->image_path) }}" class="w-full h-full object-cover" alt="">
                <button type="button"
                        onclick="markImageForDeletion(this, {{ $img->id }})"
                        class="absolute top-1 right-1 w-5 h-5 bg-black/60 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity">&times;</button>
            </div>
            @endforeach
        </div>
        <div id="delete-images-container"></div>
        @endif

        <div id="image-drop-zone"
             class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-green-400 hover:bg-green-50/30 transition-colors">
            <i data-lucide="camera" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
            <p class="text-xs text-gray-500 font-bold">クリックまたはドラッグ&ドロップで写真を追加</p>
            <p class="text-[10px] text-gray-400 mt-1">JPG, PNG, WebP / 最大5MB / 5枚まで</p>
        </div>
        <input type="file" name="images[]" id="image-input" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
        <div id="image-previews" class="flex gap-2 mt-3 overflow-x-auto pb-1"></div>
        @error('images.*')
        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
