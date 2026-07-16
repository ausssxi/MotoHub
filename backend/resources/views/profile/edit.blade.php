<x-layout>
    <x-slot:title>アカウント設定 - MotoHub</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-black mb-4 transition-colors">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i> マイページに戻る
                </a>
                <h1 class="text-2xl font-black text-gray-900">アカウント設定</h1>
            </div>

            <div class="space-y-6">
                {{-- プロフィールアイコン（アバター） --}}
                <div class="p-6 sm:p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100">
                    <div class="max-w-xl">
                        @include('profile.partials.update-avatar-form')
                    </div>
                </div>

                {{-- プロフィール情報 --}}
                <div class="p-6 sm:p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                {{-- プロフィール表示設定（公開プロフィールへの集約オプトアウト） --}}
                <div class="p-6 sm:p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-visibility-form')
                    </div>
                </div>

                {{-- パスワード変更（Googleのみユーザーには非表示） --}}
                @unless(auth()->user()->isGoogleUser() && is_null(auth()->user()->password))
                <div class="p-6 sm:p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
    </div>
</div>
@endunless

                {{-- アカウント削除 --}}
                <div class="p-6 sm:p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>