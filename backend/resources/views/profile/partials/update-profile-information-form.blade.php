<section>
    <header class="text-center mb-8">
        <h2 class="text-xl font-black text-gray-900">
            プロフィール情報
        </h2>
        <p class="mt-2 text-sm text-gray-600 font-bold">
            アカウントのプロフィール情報とメールアドレスを更新します。
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        {{-- 公開表示名（ハンドル）＝主役。サイト全体・公開ページでこの名前が表示される。いつでも変更可。 --}}
        <div>
            <x-input-label for="review_display_name" :value="__('公開表示名')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="review_display_name" name="review_display_name" type="text" maxlength="30"
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                :value="old('review_display_name', $user->review_display_name)" placeholder="例: rider_x" autofocus autocomplete="off" />
            <p class="text-[11px] text-gray-400 font-bold mt-1 ml-1">サイト内の表示・公開ページ（ガレージ/レビュー等）で使われます。本名は表示されません。</p>
            <x-input-error class="mt-2" :messages="$errors->get('review_display_name')" />
        </div>

        {{-- お名前＝従。メールの宛名にのみ使用（公開されない）。 --}}
        <div>
            <x-input-label for="name" :value="__('お名前')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="name" name="name" type="text"
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                :value="old('name', $user->name)" required autocomplete="name" />
            <p class="text-[11px] text-gray-400 font-bold mt-1 ml-1">通知メールの宛名に使います（公開されません）。</p>
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('メールアドレス')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="email" name="email" type="email" 
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-4 p-4 bg-yellow-50 rounded-xl border border-yellow-100 text-center">
                    <p class="text-sm text-yellow-800 font-bold mb-2">
                        メールアドレスが確認されていません。
                    </p>
                    <button form="send-verification" class="text-xs font-bold underline text-yellow-600 hover:text-yellow-900">
                        確認メールを再送信する
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-xs text-green-600">
                            新しい確認リンクをメールアドレスに送信しました。
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col items-center gap-4 pt-4">
            <x-primary-button class="w-full sm:w-auto min-w-[200px] justify-center bg-black hover:bg-gray-800 font-black rounded-xl px-6 py-3.5 shadow-lg active:scale-95 transition">
                {{ __('保存する') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-bold flex items-center gap-1"
                >
                    <i data-lucide="check-circle" class="w-4 h-4"></i> 保存しました
                </p>
            @endif
        </div>
    </form>
</section>