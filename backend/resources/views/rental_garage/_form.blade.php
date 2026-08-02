{{-- レンタルガレージ 投稿フォーム（駐輪場 _form と同構造） --}}
@php
    $val = fn(string $field) => old($field);
    // RentalGarage::garageTypeLabel と揃えたラベル
    $garageTypes = ['indoor' => '屋内ガレージ', 'container' => '屋外コンテナ', 'open' => '青空月極', 'other' => 'その他'];
    // 設備3択（あり/なし/不明）。value: '1'=あり / '0'=なし / ''=不明（null）
    $triState = ['1' => 'あり', '0' => 'なし', '' => '不明'];
@endphp

<div class="space-y-6">

    {{-- 防御2: ハニーポット（人間には非表示。ボットが入力すると破棄される） --}}
    <div aria-hidden="true" style="position:absolute !important; left:-9999px !important; top:-9999px !important; height:0; width:0; overflow:hidden;">
        <label>会社サイト（入力しないでください）
            <input type="text" name="company_website" tabindex="-1" autocomplete="off" value="">
        </label>
    </div>

    {{-- 施設名 --}}
    <div>
        <label for="name" class="block text-xs font-bold text-gray-700 mb-1">施設名 <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ $val('name') }}" required maxlength="150" placeholder="例: ○○バイクガレージ 高円寺"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
    </div>

    {{-- ガレージの種類 --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-2">ガレージの種類 <span class="text-red-500">*</span></label>
        <div class="grid grid-cols-2 gap-2">
            @foreach($garageTypes as $value => $label)
            <label class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer hover:bg-gray-50 transition text-sm {{ old('garage_type', 'container') === $value ? 'border-violet-500 bg-violet-50' : 'border-gray-200' }}">
                <input type="radio" name="garage_type" value="{{ $value }}" {{ old('garage_type', 'container') === $value ? 'checked' : '' }}
                    class="text-violet-600 focus:ring-violet-500">
                {{ $label }}
            </label>
            @endforeach
        </div>
    </div>

    {{-- 住所入力＋地図 --}}
    <div>
        <label for="address" class="block text-xs font-bold text-gray-700 mb-1">住所 <span class="text-red-500">*</span></label>
        <div class="flex gap-2">
            <input type="text" name="address" id="address" value="{{ $val('address') }}" required maxlength="255" placeholder="例: 東京都杉並区高円寺南1-2-3"
                class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
            <button type="button" id="btn-geocode" class="bg-gray-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-gray-700 transition shrink-0">
                住所検索
            </button>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">住所を入力して「住所検索」を押すか、地図上をクリックして位置を指定できます。</p>
    </div>

    <div id="create-map" class="w-full border border-gray-200"></div>

    {{-- 地図ピンで確定する緯度経度・都道府県・市区町村（create.js が設定） --}}
    <input type="hidden" name="latitude" id="latitude" value="{{ $val('latitude') }}">
    <input type="hidden" name="longitude" id="longitude" value="{{ $val('longitude') }}">
    <input type="hidden" name="prefecture" id="prefecture" value="{{ $val('prefecture') }}">
    <input type="hidden" name="city" id="city" value="{{ $val('city') }}">

    {{-- 運営会社・郵便番号 --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="operator" class="block text-xs font-bold text-gray-700 mb-1">運営会社（任意）</label>
            <input type="text" name="operator" id="operator" value="{{ $val('operator') }}" maxlength="100" placeholder="例: ○○ストレージ"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
        <div>
            <label for="postal_code" class="block text-xs font-bold text-gray-700 mb-1">郵便番号（任意）</label>
            <input type="text" name="postal_code" id="postal_code" value="{{ $val('postal_code') }}" maxlength="8" placeholder="例: 166-0003"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
    </div>

    {{-- 月額 --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-2">月額料金（任意）</label>
        <div class="flex items-center gap-2">
            <input type="number" name="monthly_fee_min" value="{{ $val('monthly_fee_min') }}" min="0" placeholder="下限"
                class="w-32 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
            <span class="text-xs text-gray-400">〜</span>
            <input type="number" name="monthly_fee_max" value="{{ $val('monthly_fee_max') }}" min="0" placeholder="上限"
                class="w-32 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
            <span class="text-xs text-gray-500">円/月</span>
        </div>
    </div>

    {{-- 区画サイズ・収容台数 --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="size_text" class="block text-xs font-bold text-gray-700 mb-1">区画サイズ（任意）</label>
            <input type="text" name="size_text" id="size_text" value="{{ $val('size_text') }}" maxlength="100" placeholder="例: 幅1.0m×奥行2.4m"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
        <div>
            <label for="capacity" class="block text-xs font-bold text-gray-700 mb-1">台数（任意）</label>
            <input type="number" name="capacity" id="capacity" value="{{ $val('capacity') }}" min="1" placeholder="台数"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
    </div>

    {{-- 電話・公式サイト --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="phone" class="block text-xs font-bold text-gray-700 mb-1">電話番号（任意）</label>
            <input type="text" name="phone" id="phone" value="{{ $val('phone') }}" maxlength="20" placeholder="例: 03-1234-5678"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
        <div>
            <label for="website_url" class="block text-xs font-bold text-gray-700 mb-1">公式サイトURL（任意）</label>
            <input type="url" name="website_url" id="website_url" value="{{ $val('website_url') }}" maxlength="255" placeholder="https://..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
    </div>

    {{-- 設備（あり/なし/不明の3択・チェックボックスにはしない） --}}
    <div>
        <label class="block text-xs font-bold text-gray-700 mb-2">設備（不明な場合は「不明」を選択）</label>
        <div class="space-y-3">
            @foreach(['is_24h' => '24時間出入り可', 'has_power' => '電源あり', 'has_security' => '防犯設備', 'has_shutter' => 'シャッター付き'] as $field => $label)
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-gray-700">{{ $label }}</span>
                <div class="flex gap-1.5">
                    @foreach($triState as $value => $optLabel)
                    <label class="px-3 py-1.5 border rounded-lg cursor-pointer text-xs transition {{ (string) old($field, '') === (string) $value ? 'border-violet-500 bg-violet-50 text-violet-700 font-bold' : 'border-gray-200 text-gray-500' }}">
                        <input type="radio" name="{{ $field }}" value="{{ $value }}" {{ (string) old($field, '') === (string) $value ? 'checked' : '' }} class="hidden">
                        {{ $optLabel }}
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- 備考 --}}
    <div>
        <label for="description" class="block text-xs font-bold text-gray-700 mb-1">備考（任意）</label>
        <textarea name="description" id="description" rows="3" maxlength="1000" placeholder="利用時の注意点やアクセス方法など..."
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition resize-none">{{ $val('description') }}</textarea>
    </div>
</div>
