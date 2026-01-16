<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        運営者情報 - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    <x-slot:navigation>
        <x-navigation 
            :totalListingsCount="$totalListingsCount ?? 0" 
            :showSearch="true" 
        />
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
                                        <a href="#" class="text-blue-600 hover:underline">https://your-domain.com</a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="py-4 pr-6 font-black text-black">運営者</th>
                                    <td class="py-4 text-gray-600">MotoHub 運営事務局</td>
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

                <section class="bg-gray-50 p-6 sm:p-8 rounded-2xl border border-gray-100">
                    <h3 class="text-sm font-black text-black mb-3">お問い合わせについて</h3>
                    <p class="text-xs sm:text-sm text-gray-500 leading-relaxed mb-6">
                        当サイトの運営に関するお問い合わせ、または権利関係のご相談については「お問い合わせ」ページよりご連絡ください。
                    </p>
                    <a href="/contact" class="inline-flex items-center justify-center bg-black text-white px-6 py-3 rounded-xl text-xs font-black hover:bg-gray-800 transition-all uppercase tracking-widest">
                        お問い合わせはこちら <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </section>

            </div>
        </div>
    </div>
</x-layout>