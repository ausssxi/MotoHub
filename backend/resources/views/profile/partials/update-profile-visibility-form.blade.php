<section>
    <header class="text-center mb-8">
        <h2 class="text-xl font-black text-gray-900">
            プロフィール表示設定
        </h2>
        <p class="mt-2 text-sm text-gray-600 font-bold">
            公開プロフィールページに、あなたの投稿をまとめて表示するかを選べます（本名は表示されません）。
        </p>
    </header>

    @if(session('status') === 'visibility-updated')
        <div class="mb-6 p-3 bg-green-50 text-green-700 text-sm font-bold rounded-xl border border-green-200 text-center">
            表示設定を保存しました。
        </div>
    @endif

    <form method="post" action="{{ route('profile.visibility') }}" class="space-y-6">
        @csrf
        @method('patch')

        <label class="flex items-start gap-3 cursor-pointer select-none">
            {{-- チェックボックス未送信時も false で更新されるよう hidden を併置 --}}
            <input type="hidden" name="profile_show_parking_reviews" value="0">
            <input type="checkbox" name="profile_show_parking_reviews" value="1"
                   {{ $user->profile_show_parking_reviews ? 'checked' : '' }}
                   class="w-5 h-5 mt-0.5 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
            <span>
                <span class="text-sm font-black text-gray-800">駐車場レビューをプロフィールに表示する</span>
                <span class="block text-xs text-gray-500 font-bold mt-0.5">あなたが投稿した駐車場レビューを公開プロフィールにまとめて表示します。</span>
            </span>
        </label>

        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-black px-6 py-2.5 rounded-xl shadow-lg transition active:scale-95">
                保存する
            </button>
        </div>
    </form>
</section>
