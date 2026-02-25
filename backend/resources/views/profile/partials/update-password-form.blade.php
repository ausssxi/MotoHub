<section>
    <header class="text-center mb-8">
        <h2 class="text-xl font-black text-gray-900">
            パスワードの変更
        </h2>
        <p class="mt-2 text-sm text-gray-600 font-bold">
            安全のため、長くて複雑なパスワードを使用することをおすすめします。
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="current_password" :value="__('現在のパスワード')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="current_password" name="current_password" type="password" 
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('新しいパスワード')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="password" name="password" type="password" 
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('新しいパスワード（確認）')" class="font-bold text-gray-500 ml-1 mb-1" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" 
                class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-base font-bold focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col items-center gap-4 pt-4">
            <x-primary-button class="w-full sm:w-auto min-w-[200px] justify-center bg-black hover:bg-gray-800 font-black rounded-xl px-6 py-3.5 shadow-lg active:scale-95 transition">
                {{ __('変更を保存') }}
            </x-primary-button>

            @if (session('status') === 'password-updated')
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