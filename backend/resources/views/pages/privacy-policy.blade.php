<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        プライバシーポリシー - MotoHub
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
            
            {{-- 戻るリンク --}}
            <div class="mb-10">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    戻る
                </a>
            </div>

            {{-- ページヘッダー --}}
            <div class="mb-16">
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-4 tracking-tighter">プライバシーポリシー</h1>
                <p class="text-gray-400 text-sm font-medium">Privacy Policy</p>
            </div>

            {{-- コンテンツエリア --}}
            <div class="space-y-12 text-gray-700">
                
                <section>
                    <h2 class="text-lg font-black text-black mb-4">広告の配信について</h2>
                    <p class="text-sm sm:text-base leading-relaxed mb-4">
                        当サイトは、第三者配信の広告サービス「Google AdSense（グーグルアドセンス）」を利用しています。
                    </p>
                    <p class="text-sm sm:text-base leading-relaxed">
                        広告配信事業者は、ユーザーの興味に応じた商品やサービスの広告を表示するため、当サイトや他サイトへのアクセスに関する情報 「Cookie」（氏名、住所、メールアドレス、電話番号は含まれません）を使用することがあります。
                    </p>
                    <p class="text-sm sm:text-base leading-relaxed mt-4">
                        Cookie（クッキー）を無効にする設定およびGoogleアドセンスに関する詳細は「<a href="https://policies.google.com/technologies/ads?hl=ja" target="_blank" class="text-blue-600 underline">広告 – ポリシーと規約 – Google</a>」をご覧ください。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">アクセス解析ツールについて</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトでは、Googleによるアクセス解析ツール「Googleアナリティクス」を利用しています。このGoogleアナリティクスはトラフィックデータの収集のためにクッキー（Cookie）を使用しています。このトラフィックデータは匿名で収集されており、個人を特定するものではありません。
                    </p>
                    <p class="text-sm sm:text-base leading-relaxed mt-4">
                        この機能はクッキー（Cookie）を無効にすることで収集を拒否することが出来ますので、お使いのブラウザの設定をご確認ください。この規約に関しての詳細は「<a href="https://marketingplatform.google.com/about/analytics/terms/jp/" target="_blank" class="text-blue-600 underline">Googleアナリティクス利用規約</a>」をご覧ください。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">個人情報の利用目的</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトでは、お問い合わせや記事へのコメントの際、名前やメールアドレス等の個人情報を入力いただく場合がございます。取得した個人情報は、お問い合わせに対する回答や必要な情報を電子メールなどをでご連絡する場合に利用させていただくものであり、これらの目的以外では利用いたしません。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">個人情報の第三者への開示</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトでは、個人情報は適切に管理し、以下に該当する場合を除いて第三者に開示することはありません。
                    </p>
                    <ul class="list-disc list-inside mt-4 space-y-2 text-sm sm:text-base ml-2">
                        <li>本人のご了解がある場合</li>
                        <li>法令等への協力のため、開示が必要となる場合</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">免責事項</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトからのリンクやバナーなどで移動したサイトで提供される情報、サービス等について一切の責任を負いません。
                    </p>
                    <p class="text-sm sm:text-base leading-relaxed mt-4">
                        また当サイトのコンテンツ・情報について、できる限り正確な情報を提供するように努めておりますが、正確性や安全性を保証するものではありません。情報の更新が遅れる場合もございます。当サイトに掲載された内容によって生じた損害等の一切の責任を負いかねますのでご了承ください。
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black text-black mb-4">著作権について</h2>
                    <p class="text-sm sm:text-base leading-relaxed">
                        当サイトで掲載している文章や画像などにつきましては、無断転載することを禁止します。当サイトは著作権や肖像権の侵害を目的としたものではありません。著作権や肖像権に関して問題がございましたら、お問い合わせフォームよりご連絡ください。迅速に対応いたします。
                    </p>
                </section>

                <div class="pt-10 border-t border-gray-100 text-[10px] text-gray-400 font-bold uppercase tracking-widest text-right">
                    策定日：2024年4月1日
                </div>

            </div>
        </div>
    </div>
</x-layout>