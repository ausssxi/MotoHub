<section>
    <header class="text-center mb-8">
        <h2 class="text-xl font-black text-gray-900">
            プロフィールアイコン
        </h2>
        <p class="mt-2 text-sm text-gray-600 font-bold">
            公開ページ（ガレージ・レビュー・コメント）で表示されるアイコンです。設定は任意です。
        </p>
    </header>

    <div class="flex flex-col items-center gap-5">
        {{-- 現在のアイコン（未設定ならイニシャル/汎用アイコン） --}}
        <x-user-avatar :user="$user" :size="16" class="ring-4 ring-gray-50 shadow" />

        {{-- アップロード（別フォーム＝画像処理を伴うため名前/メール更新と分離） --}}
        <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data"
              class="w-full max-w-sm flex flex-col items-center gap-3">
            @csrf

            <label class="w-full cursor-pointer">
                <span class="sr-only">画像ファイルを選択</span>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                       class="block w-full text-sm text-gray-600 font-bold
                              file:mr-3 file:py-2.5 file:px-5 file:rounded-xl file:border-0
                              file:text-sm file:font-black file:bg-gray-900 file:text-white hover:file:bg-gray-800
                              file:cursor-pointer">
            </label>
            <p class="text-[11px] text-gray-400 font-bold text-center">
                JPEG / PNG / WebP / HEIC・最大 {{ (int) (config('avatar.max_upload_kb') / 1024) }}MB。<br>
                正方形に切り抜いて保存します。位置情報（EXIF）は自動で削除されます。
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('avatar')" />

            <x-primary-button class="w-full sm:w-auto min-w-[180px] justify-center bg-black hover:bg-gray-800 font-black rounded-xl px-6 py-3 shadow-lg active:scale-95 transition">
                {{ __('アイコンを更新') }}
            </x-primary-button>
        </form>

        {{-- 削除（設定済みのときだけ） --}}
        @if($user->avatar_path)
            <form method="post" action="{{ route('profile.avatar.destroy') }}"
                  onsubmit="return confirm('プロフィールアイコンを削除しますか？');">
                @csrf
                @method('delete')
                <button type="submit" class="text-xs font-black text-gray-400 hover:text-red-500 transition-colors inline-flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> アイコンを削除する
                </button>
            </form>
        @endif

        @if (session('status') === 'avatar-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
               class="text-sm text-green-600 font-bold flex items-center gap-1">
                <i data-lucide="check-circle" class="w-4 h-4"></i> アイコンを更新しました
            </p>
        @elseif (session('status') === 'avatar-removed')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
               class="text-sm text-gray-500 font-bold flex items-center gap-1">
                <i data-lucide="check-circle" class="w-4 h-4"></i> アイコンを削除しました
            </p>
        @endif
    </div>
</section>
