<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        利用規約・免責事項 - MotoHub
    </x-slot:title>

    {{-- 2. ナビゲーション --}}
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-white min-h-[calc(100vh-64px)] py-12 sm:py-20">
        <div class="max-w-3xl mx-auto px-6">
            
            {{-- 戻るリンク --}}
            <div class="mb-10">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    戻る
                </a>
            </div>

            {{-- ページヘッダー --}}
            <div class="mb-16">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-4 tracking-tighter">利用規約・免責事項</h1>
                <p class="text-gray-400 text-sm font-medium">Terms of Service & Disclaimer</p>
            </div>

            {{-- コンテンツエリア --}}
            <div class="space-y-12 text-gray-700">
                
                <section>
                    <h2 class="text-lg font-black text-black mb-4">第1条（適用）</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        本規約は、MotoHub（以下、「当サイト」）が提供するサービス（以下、「本サービス」）の利用条件を定めるものです。本サービスを利用するすべてのユーザーは、本規約に同意したものとみなされます。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">第2条（免責事項）</h2>
                    <ul class="space-y-6 text-sm sm:text-base leading-relaxed">
                        <li class="flex flex-col gap-2">
                            <span class="font-black text-black text-xs uppercase tracking-tighter">1. 情報の正確性について</span>
                            当サイトに掲載されている車両情報、価格、店舗情報等は、提携先または外部サイトから自動的に取得・集約されたものです。情報の正確性、最新性、有用性、合法性等について、運営者は一切の保証をいたしません。
                        </li>
                        <li class="flex flex-col gap-2">
                            <span class="font-black text-black text-xs uppercase tracking-tighter">2. 売買契約について</span>
                            当サイトはバイクの直接販売を行っているものではありません。売買契約はユーザーと各販売店との間で直接行われるものであり、契約内容や車両状態に関するトラブル、損害等について、運営者は一切の責任を負いません。
                        </li>
                        <li class="flex flex-col gap-2">
                            <span class="font-black text-black text-xs uppercase tracking-tighter">3. サービスの中断・停止</span>
                            保守作業やシステムの故障、その他不可抗力により、予告なく本サービスの提供を中断・停止することがあります。これによって生じたユーザーの損害について、一切の責任を負いません。
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">第3条（禁止事項）</h2>
                    <p class="text-sm sm:text-base leading-relaxed mb-4">ユーザーは、本サービスの利用にあたり、以下の行為を行ってはならないものとします。</p>
                    <ul class="list-disc list-inside space-y-2 text-sm sm:text-base ml-2">
                        <li>当サイトの情報を不正にクローリング、スクレイピングする行為</li>
                        <li>当サイトの運営を妨害する恐れのある行為</li>
                        <li>公序良俗に反する行為、または法令に違反する行為</li>
                        <li>他のユーザーまたは第三者に不利益、損害、不快感を与える行為</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">第4条（著作権・知的財産権）</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        本サービスに含まれるテキスト、画像、デザイン等の著作権および知的財産権は、運営者または正当な権利者に帰属します。許可なくこれらを複製、転載、改変、配布することを禁止します。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">第5条（規約の変更）</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトは、ユーザーの承諾を得ることなく、いつでも本規約の内容を変更できるものとします。変更後の規約は、当サイト上に表示した時点から効力を生じるものとします。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">第6条（準拠法・裁判管轄）</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        本規約の解釈にあたっては日本法を準拠法とし、本サービスに関して紛争が生じた場合には、運営者の所在地を管轄する裁判所を専属的合意管轄とします。
                    </p>
                </section>

                <div class="pt-10 border-t border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-widest text-right">
                    策定日：2024年4月1日
                </div>

            </div>
        </div>
    </div>
</x-layout>