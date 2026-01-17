{{-- アドセンス審査を意識した、信頼性の高いフッターコンポーネント --}}
<footer class="bg-white border-t border-gray-100 pt-16 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col items-center text-center">
            
            <!-- サイトロゴ & 紹介 -->
            <div class="mb-10 max-w-2xl">
                <div class="flex items-center justify-center gap-2 mb-4">
                    <i data-lucide="bike" class="w-6 h-6 text-black"></i>
                    <span class="text-xl font-black tracking-tighter">MotoHub</span>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed font-medium">
                    MotoHub（モトハブ）は、日本中のバイク販売店から中古車・新車情報を集約し、<br class="hidden sm:block">
                    あなたに最適な一台を最速で見つけるためのバイク一括検索プラットフォームです。
                </p>
            </div>

            <!-- リンクメニュー -->
            <nav class="mb-10">
                <ul class="flex flex-wrap justify-center gap-x-8 gap-y-4 text-xs font-bold text-gray-500 uppercase tracking-widest">
                    <li>
                        <a href="{{ route('pages.about') }}" class="footer-link">運営者情報</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.contact') }}" class="footer-link">お問い合わせ</a>
                    </li>

                    {{-- スマートフォン表示時のみ、ここで強制改行を入れる --}}
                    <div class="basis-full h-0 sm:hidden"></div>

                    <li>
                        <a href="{{ route('pages.privacy-policy') }}" class="footer-link">プライバシーポリシー</a>
                    </li>
                    <li>
                        <a href="{{ route('pages.terms') }}" class="footer-link">利用規約・免責事項</a>
                    </li>
                </ul>
            </nav>

            <!-- コピーライト -->
            <div class="pt-8 border-t border-gray-200/50 w-full">
                <p class="text-[10px] text-gray-300 font-bold tracking-widest uppercase">
                    &copy; {{ date('Y') }} MotoHub Project - All Rights Reserved.
                </p>
            </div>
        </div>
    </div>
</footer>