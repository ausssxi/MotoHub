<section>
    <header class="text-center mb-8">
        <h2 class="text-xl font-black text-gray-900">
            プロフィールアイコン
        </h2>
        <p class="mt-2 text-sm text-gray-600 font-bold">
            公開ページ（ガレージ・レビュー・コメント）で表示されるアイコンです。設定は任意です。
        </p>
    </header>

    {{-- ファイル選択で即アップロード（1アクション）。サーバー側は無改変＝redirect(302)/422/429 を
         fetch(redirect:'manual', Accept:json) で見分けて、成功はリロード・失敗はインライン表示する。 --}}
    <div class="flex flex-col items-center gap-5"
         x-data="{
            uploading: false,
            error: '',
            async submit(e) {
                const input = e.target;
                if (! input.files || ! input.files.length) return;
                this.error = '';
                this.uploading = true;
                try {
                    const res = await fetch('{{ route('profile.avatar.update') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(this.$refs.form),
                        redirect: 'manual',
                    });
                    // 成功: サーバーは 302 リダイレクト → opaqueredirect。追わずにリロード（フラッシュ温存＝緑トースト＋新アイコン）。
                    if (res.type === 'opaqueredirect' || res.ok) { window.location.reload(); return; }
                    if (res.status === 422) {
                        const data = await res.json().catch(() => ({}));
                        this.error = (data.errors && data.errors.avatar && data.errors.avatar[0]) || 'アップロードに失敗しました。';
                    } else if (res.status === 429) {
                        this.error = 'アップロードが集中しています。少し時間をおいて再度お試しください。';
                    } else {
                        this.error = 'アップロードに失敗しました。時間をおいて再度お試しください。';
                    }
                } catch (err) {
                    this.error = '通信エラーが発生しました。接続を確認してください。';
                } finally {
                    this.uploading = false;
                    input.value = ''; // 同じファイルを選び直しても change が再発火するようにリセット
                }
            }
         }">
        {{-- 現在のアイコン（アップロード中はスピナーを重ねる） --}}
        <div class="relative">
            <x-user-avatar :user="$user" :size="16" class="ring-4 ring-gray-50 shadow" />
            <div x-show="uploading" x-cloak class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center">
                <svg class="animate-spin w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </div>

        <form x-ref="form" method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data"
              class="w-full max-w-sm flex flex-col items-center gap-3">
            @csrf

            <label class="w-full cursor-pointer">
                <span class="sr-only">画像ファイルを選択</span>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                       @change="submit($event)" :disabled="uploading"
                       class="block w-full text-sm text-gray-600 font-bold
                              file:mr-3 file:py-2.5 file:px-5 file:rounded-xl file:border-0
                              file:text-sm file:font-black file:bg-gray-900 file:text-white hover:file:bg-gray-800
                              file:cursor-pointer disabled:opacity-50">
            </label>

            {{-- JS 無効時のフォールバック（選択後に手動送信できる） --}}
            <noscript>
                <button type="submit" class="text-sm font-black text-white bg-black hover:bg-gray-800 rounded-xl px-6 py-2.5 transition">アイコンを更新</button>
            </noscript>

            <p class="text-[11px] text-gray-400 font-bold text-center">
                ファイルを選ぶとすぐに反映されます。JPEG / PNG / WebP / HEIC・最大 {{ (int) (config('avatar.max_upload_kb') / 1024) }}MB。<br>
                正方形に切り抜いて保存します。位置情報（EXIF）は自動で削除されます。
            </p>

            {{-- アップロード中の表示（二重送信防止のフィードバック） --}}
            <p x-show="uploading" x-cloak class="text-sm text-gray-500 font-bold flex items-center gap-1">
                アップロード中…
            </p>

            {{-- エラー（サイズ超過・非画像・throttle 等）を選択時でもインライン表示 --}}
            <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600 font-bold text-center"></p>
            {{-- 非JS/フルリロード時のサーバー側エラー --}}
            <x-input-error class="mt-1" :messages="$errors->get('avatar')" />
        </form>

        {{-- 削除（設定済みのときだけ・無改変） --}}
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
