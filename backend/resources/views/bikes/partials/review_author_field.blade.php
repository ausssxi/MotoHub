{{--
    レビュー投稿者名フィールド（auth分岐）。
    ⚠️ 重要: ここでは絶対に auth()->user()->name（本名が入りうる）を出力しない。
       公開表示はユーザーが設定した review_display_name（公開ハンドル）のみ。
    引数: $inputClass / $labelClass（フォーム毎のスタイル）
--}}
@php
    $inputClass = $inputClass ?? 'w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none';
    $labelClass = $labelClass ?? 'block text-xs font-bold text-gray-500 mb-1';
@endphp

@auth
    @php $rdn = auth()->user()->review_display_name; @endphp
    @if(!empty($rdn))
        {{-- ハンドル設定済み: 読み取り専用表示（変更不可） --}}
        <div>
            <label class="{{ $labelClass }}">公開表示名</label>
            <p class="text-sm font-bold text-gray-800 py-2">この名前で投稿されます：<span class="text-blue-600">{{ $rdn }}</span></p>
        </div>
    @else
        {{-- 未設定: 公開ハンドルを入力（本名でprefillしない） --}}
        <div>
            <label class="{{ $labelClass }}">公開表示名 <span class="text-[10px] font-normal text-gray-400">（公開されます・設定後は変更できません）</span></label>
            <input type="text" name="review_handle" value="{{ old('review_handle') }}" required maxlength="30"
                   class="{{ $inputClass }}" placeholder="公開用の表示名（例: rider_x）">
        </div>
    @endif
@else
    {{-- ゲスト: 従来どおりニックネーム --}}
    <div>
        <label class="{{ $labelClass }}">ニックネーム</label>
        <input type="text" name="nickname" value="{{ old('nickname') }}" maxlength="50"
               class="{{ $inputClass }}" placeholder="名無しライダー">
    </div>
@endauth
