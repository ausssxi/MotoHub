<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        運営者情報 - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-white min-h-[calc(100vh-64px)] py-12 sm:py-20">
        <div class="max-w-3xl mx-auto px-6">
            
            {{-- パンくず・戻るリンク --}}
            <div class="mb-10">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    戻る
                </a>
            </div>

            {{-- ページヘッダー --}}
            <div class="mb-16">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-4 tracking-tighter">運営者情報</h1>
                <p class="text-gray-400 text-sm font-medium">About MotoHub</p>
            </div>

            {{-- コンテンツエリア --}}
            <div class="space-y-12">
                
                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">サイトの目的</h2>
                    <p class="text-sm sm:text-base text-gray-700 leading-relaxed font-medium">
                        MotoHub（モトハブ）は、日本中のバイク販売店から中古車・新車情報を集約し、ユーザーが効率よく希望の車両を検索できる環境を提供することを目的としています。<br><br>
                        膨大な情報の中から、「価格」「地域」「モデル」などの条件を横断的に検索可能にすることで、ライダーの皆様が理想の一台に最速で辿り着けるプラットフォームを目指しています。
                    </p>
                </section>

                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">運営体制</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black w-1/3">サイト名</th>
                                    <td class="py-4 text-gray-600">MotoHub（モトハブ）</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">サイトURL</th>
                                    <td class="py-4 text-gray-600">
                                        {{-- サイトのURLを記載 --}}
                                        <a href="{{ config('app.url') }}" class="text-blue-600 hover:underline">{{ config('app.url') }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">運営者</th>
                                    <td class="py-4 text-gray-600">内田厚（うちだあつし）</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">所在地</th>
                                    <td class="py-4 text-gray-600">
                                        日本（神奈川県）<br class="sm:hidden">
                                        <span class="sm:ml-2 text-xs text-gray-400">※詳細は、お問い合わせに応じて遅滞なく開示いたします</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">開発者について</h2>
                    <p class="text-sm sm:text-base text-gray-700 leading-relaxed font-medium">
                        MotoHubは、バイク好きのエンジニアが一人で開発・運営しています。<br><br>
                        花屋として働きながら、独学でプログラミングを学びました。
                        その後Web開発会社に転職し、現在は本業の傍らMotoHubの開発を続けています。<br><br>
                        昔はTW200に乗っていました。
                        「バイクを探すのに何サイトも開くのが面倒」という自分自身の経験が、MotoHub開発のきっかけです。
                    </p>
                </section>

                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">MotoHubのデータについて</h2>
                    <p class="text-sm sm:text-base text-gray-700 leading-relaxed font-medium mb-6">
                        MotoHubは全国のバイク販売店から中古車・新車の在庫情報を毎日収集しています。
                    </p>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black w-1/3">掲載台数</th>
                                    <td class="py-4 text-gray-600">約115,000台（毎日更新）</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">累計データ</th>
                                    <td class="py-4 text-gray-600">約228,000台（売り切れ含む）</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">駐車場データ</th>
                                    <td class="py-4 text-gray-600">約39,000件</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">対応メーカー</th>
                                    <td class="py-4 text-gray-600">国内4社+海外メーカー</td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">データ更新</th>
                                    <td class="py-4 text-gray-600">毎日自動更新</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-sm sm:text-base text-gray-700 leading-relaxed font-medium">
                        売れ筋ランキングは、実際に販売された車両のデータから集計しています。
                        編集部の主観やアクセス数ではなく、実売データに基づいたランキングです。
                    </p>
                </section>

                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">技術スタック</h2>
                    <p class="text-sm sm:text-base text-gray-700 leading-relaxed font-medium mb-4">
                        MotoHubは以下の技術で構築されています。
                    </p>
                    <ul class="space-y-2 text-sm sm:text-base text-gray-700 font-medium">
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">バックエンド</span><span class="text-gray-600">Laravel / PHP 8.3</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">データベース</span><span class="text-gray-600">MySQL 8</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">全文検索</span><span class="text-gray-600">Meilisearch</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">キャッシュ</span><span class="text-gray-600">Redis</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">インフラ</span><span class="text-gray-600">さくらVPS / Docker</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">CDN</span><span class="text-gray-600">Cloudflare</span></li>
                        <li class="flex gap-3"><span class="font-black text-black w-32 shrink-0">AI</span><span class="text-gray-600">OpenAI GPT-4o（車種判定AI）</span></li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xs font-black text-gray-300 uppercase tracking-[0.2em] mb-6 border-b border-gray-100 pb-2">外部リンク・開発情報</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="https://x.com/motohub_jp" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-300 hover:bg-gray-50 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-black text-white flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-gray-400 uppercase tracking-wider">X (Twitter)</p>
                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors">@motohub_jp</p>
                            </div>
                        </a>
                        <a href="https://zenn.dev/ausssxi" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-300 hover:bg-gray-50 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-blue-500 text-white flex items-center justify-center flex-shrink-0">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-gray-400 uppercase tracking-wider">Zenn</p>
                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors">技術記事</p>
                            </div>
                        </a>
                        <a href="https://github.com/ausssxi/MotoHub" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-300 hover:bg-gray-50 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-gray-900 text-white flex items-center justify-center flex-shrink-0">
                                <i data-lucide="github" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-gray-400 uppercase tracking-wider">GitHub</p>
                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors">ausssxi/MotoHub</p>
                            </div>
                        </a>
                        <a href="mailto:info@motohub.jp"
                           class="flex items-center gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-300 hover:bg-gray-50 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center flex-shrink-0">
                                <i data-lucide="mail" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-black text-gray-400 uppercase tracking-wider">お問い合わせ</p>
                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-600 transition-colors">info@motohub.jp</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="bg-gray-50 p-6 sm:p-8 rounded-2xl border border-gray-100">
                    <h3 class="text-sm font-black text-black mb-3">お問い合わせについて</h3>
                    <p class="text-xs sm:text-sm text-gray-500 leading-relaxed mb-6">
                        当サイトの運営に関するお問い合わせ、または権利関係のご相談については「お問い合わせ」ページよりご連絡ください。
                    </p>
                    <a href="contact" class="inline-flex items-center justify-center bg-black text-white px-6 py-3 rounded-xl text-xs font-black hover:bg-gray-800 transition uppercase tracking-widest">
                        お問い合わせはこちら <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </section>

            </div>
        </div>
    </div>
</x-layout>