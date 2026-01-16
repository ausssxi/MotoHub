<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        お問い合わせ - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-white min-h-[calc(100vh-64px)] py-12 sm:py-20">
        <div class="max-w-2xl mx-auto px-6">
            
            {{-- 戻るリンク --}}
            <div class="mb-10">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    戻る
                </a>
            </div>

            {{-- ページヘッダー --}}
            <div class="mb-12">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-4 tracking-tighter">お問い合わせ</h1>
                <p class="text-gray-400 text-sm font-medium leading-relaxed">
                    MotoHubに関するご意見・ご要望、運営へのお問い合わせはこちらのフォームより受け付けております。
                </p>
            </div>

            {{-- 注意事項 --}}
            <div class="bg-blue-50 border border-blue-100 p-5 rounded-2xl mb-12 flex gap-4">
                <div class="flex-shrink-0">
                    <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                </div>
                <div class="text-xs sm:text-sm text-blue-700 leading-relaxed font-medium">
                    <p class="font-black mb-1">車両の在庫・状態確認について</p>
                    当サイトは一括検索サービスであり、直接販売は行っておりません。各車両の在庫確認や詳細については、VIEW INFOより遷移先の「販売店」へ直接お問い合わせください。
                </div>
            </div>

            {{-- お問い合わせフォーム --}}
            <form action="#" method="POST" class="space-y-8">
                @csrf
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <!-- お名前 -->
                    <div class="space-y-2">
                        <label for="name" class="text-xs font-black text-black uppercase tracking-widest">お名前</label>
                        <input type="text" id="name" name="name" required
                            class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black transition-all"
                            placeholder="例：山田 太郎">
                    </div>
                    <!-- メールアドレス -->
                    <div class="space-y-2">
                        <label for="email" class="text-xs font-black text-black uppercase tracking-widest">メールアドレス</label>
                        <input type="email" id="email" name="email" required
                            class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black transition-all"
                            placeholder="example@motohub.jp">
                    </div>
                </div>

                <!-- 項目 -->
                <div class="space-y-2">
                    <label for="category" class="text-xs font-black text-black uppercase tracking-widest">お問い合わせ項目</label>
                    <select id="category" name="category" required
                        class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black transition-all cursor-pointer">
                        <option value="">選択してください</option>
                        <option value="feedback">サービスへのご要望・改善案</option>
                        <option value="report">不具合・誤情報の報告</option>
                        <option value="business">ビジネス・提携に関するご相談</option>
                        <option value="other">その他</option>
                    </select>
                </div>

                <!-- 内容 -->
                <div class="space-y-2">
                    <label for="message" class="text-xs font-black text-black uppercase tracking-widest">お問い合わせ内容</label>
                    <textarea id="message" name="message" rows="6" required
                        class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black transition-all resize-none"
                        placeholder="詳細をご記入ください"></textarea>
                </div>

                <!-- 送信ボタン -->
                <div class="pt-4">
                    <button type="submit" 
                        class="w-full sm:w-auto bg-black text-white px-10 py-4 rounded-xl text-sm font-black hover:bg-gray-800 transition-all active:scale-95 shadow-lg shadow-black/10">
                        メッセージを送信する
                    </button>
                </div>
            </form>

            {{-- 補足事項 --}}
            <p class="mt-12 text-[10px] text-gray-400 leading-relaxed text-center">
                ※通常3営業日以内にご回答させていただきますが、内容によってはお時間をいただく場合や、<br class="hidden sm:block">
                回答を差し控えさせていただく場合がございます。あらかじめご了承ください。
            </p>
        </div>
    </div>
</x-layout>