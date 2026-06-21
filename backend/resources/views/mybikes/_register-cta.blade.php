{{-- 公開ガレージ/プロフィールの登録CTA（★未ログインのみ）。Xからの着地→登録ファネル。静的リンク。 --}}
@guest
<div class="bg-gradient-to-r from-pink-50 to-rose-50 rounded-3xl p-6 border border-pink-100 text-center">
    <i data-lucide="bike" class="w-8 h-8 text-pink-400 mx-auto mb-2"></i>
    <h3 class="text-lg font-black text-gray-900 mb-1">あなたも愛車を記録しよう</h3>
    <p class="text-xs text-gray-500 mb-4">レシートを撮るだけで燃費・維持費が残せる。無料。</p>
    <a href="{{ route('register') }}" class="inline-block bg-pink-600 text-white font-black text-sm px-8 py-3 rounded-xl hover:bg-pink-700 transition-colors shadow-lg active:scale-95">
        無料で始める
    </a>
</div>
@endguest
