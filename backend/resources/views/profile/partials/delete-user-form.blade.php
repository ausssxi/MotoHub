<section class="space-y-6">
    <header class="text-center">
        <h2 class="text-xl font-black text-gray-900">
            アカウントの削除
        </h2>

        <p class="mt-2 text-sm text-gray-600 font-bold">
            アカウントを削除すると、すべてのデータが完全に削除されます。<br class="hidden sm:inline">
            削除する前に、保存しておきたいデータがあればバックアップを取ってください。
        </p>
    </header>

    <div class="flex justify-center pt-4">
        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="bg-red-600 hover:bg-red-500 font-black rounded-xl px-6 py-3 w-full sm:w-auto min-w-[200px] justify-center transition"
        >{{ __('アカウントを削除する') }}</x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 text-center">
            @csrf
            @method('delete')

            <div class="mx-auto w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-600 mb-4">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>

            <h2 class="text-xl font-black text-gray-900 mb-2">
                本当に削除しますか？
            </h2>

            <p class="text-sm text-gray-500 font-bold mb-6">
                アカウントを削除すると、すべてのデータが完全に削除され、元に戻すことはできません。
            </p>

            <div class="mt-6 text-left">
                <x-input-label for="password" value="{{ __('パスワード') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-red-500 focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 text-center"
                    placeholder="{{ __('パスワードを入力して確認') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-center" />
            </div>

            <div class="mt-8 flex flex-col-reverse sm:flex-row justify-center gap-3">
                <x-secondary-button x-on:click="$dispatch('close')" class="rounded-xl font-bold py-3 justify-center">
                    {{ __('キャンセル') }}
                </x-secondary-button>

                <x-danger-button class="bg-red-600 hover:bg-red-500 font-black rounded-xl py-3 justify-center shadow-lg">
                    {{ __('アカウントを削除') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>