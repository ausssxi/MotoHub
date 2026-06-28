<x-layout>
    {{-- SEO: title / metaDescription に「中古バイク データAPI」「MotoHub」を含める --}}
    <x-slot:title>中古バイク データAPI（流通台数データ提供） | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubは全国の中古バイク掲載情報を集約し、排気量クラス別の流通台数（在庫量）データをAPIで提供しています。メディア・データ配信・開発での「中古バイク API」「バイク 台数 データ」活用に。出典明記での利用歓迎・現在無料。</x-slot:metaDescription>
    <x-slot:ogImage>{{ asset('images/about-ogp.png') }}</x-slot:ogImage>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-white min-h-[calc(100vh-64px)] py-12 sm:py-16">
        <div class="max-w-3xl mx-auto px-6">

            {{-- 戻るリンク --}}
            <div class="mb-10">
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    戻る
                </a>
            </div>

            {{-- 1. ヒーロー / 概要 --}}
            <section class="mb-16">
                <p class="text-xs font-black text-blue-500 uppercase tracking-widest mb-3">MotoHub Data API</p>
                <h1 class="text-3xl sm:text-4xl font-black text-black mb-5 tracking-tighter leading-tight">
                    MotoHub データAPI<br class="hidden sm:block">
                    <span class="text-2xl sm:text-3xl">― 中古バイクの流通台数データを提供しています</span>
                </h1>
                <p class="text-gray-500 text-sm sm:text-base font-medium leading-relaxed">
                    MotoHubは、全国の中古バイク掲載情報を集約し、車種別の<strong class="text-black">流通台数（在庫量）データ</strong>をAPIで提供しています。メディアでの記事制作、データ配信、アプリ・サービス開発などでの活用を想定しています。
                </p>
            </section>

            {{-- 2. 何ができるか --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-black mb-6 tracking-tight">何ができるか</h2>
                <div class="space-y-4">
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 flex gap-4">
                        <i data-lucide="bar-chart-3" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-gray-700 font-medium leading-relaxed">
                            排気量クラス別の<strong class="text-black">「流通台数ランキング」</strong>を取得できます。
                        </p>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 flex gap-4">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-gray-700 font-medium leading-relaxed">
                            指標は<strong class="text-black">「現在掲載中の在庫台数（＝いま中古市場に出ている量）」</strong>です。<strong class="text-black">"売れた台数"ではありません</strong>。中古市場における流通量・人気の参考指標としてご利用いただけます。
                        </p>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 flex gap-4">
                        <i data-lucide="quote" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-gray-700 font-medium leading-relaxed">
                            出典「<strong class="text-black">MotoHub調べ</strong>」を明記いただければ、記事・動画・配信などでの利用を歓迎します。
                        </p>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 flex gap-4">
                        <i data-lucide="youtube" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <p class="text-sm text-gray-700 font-medium leading-relaxed">
                            すでに<strong class="text-black">データ系のバイクYouTuberへの提供を開始</strong>しています。
                        </p>
                    </div>
                </div>
            </section>

            {{-- 3. API仕様 --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-black mb-6 tracking-tight">API仕様</h2>

                {{-- 共通仕様（全エンドポイント共通） --}}
                <h3 class="text-base font-black text-black mb-3 tracking-tight">共通仕様</h3>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top w-32">ベースURL</td>
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">https://motohub.jp/api/v1</code></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top">認証</td>
                                <td class="px-4 py-3 text-gray-700 font-medium"><code class="bg-gray-100 text-blue-600 font-bold px-1.5 py-0.5 rounded text-xs">X-API-Key</code> ヘッダ（または <code class="bg-gray-100 text-blue-600 font-bold px-1.5 py-0.5 rounded text-xs">?api_key=</code> クエリ）。キーは申請後に発行</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top">レート制限</td>
                                <td class="px-4 py-3 text-gray-700 font-medium">10リクエスト/分・100リクエスト/日（キー単位）</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top">レスポンス形式</td>
                                <td class="px-4 py-3 text-gray-700 font-medium">JSON（UTF-8）</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top">source</td>
                                <td class="px-4 py-3 text-gray-700 font-medium">全レスポンス共通で <code class="bg-gray-100 text-blue-600 font-bold px-1.5 py-0.5 rounded text-xs">"MotoHub (motohub.jp)"</code></td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-black text-gray-500 whitespace-nowrap align-top">データの鮮度</td>
                                <td class="px-4 py-3 text-gray-700 font-medium">約1時間キャッシュ（概ね最新）。相場推移は「最新の日次平均 vs 約30日前」が基準</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed mb-10 bg-amber-50 border border-amber-100 rounded-xl p-3">
                    ※APIキーは申請（下記お問い合わせフォーム）後に個別発行いたします。実在のキーやその生成方法は本ページには記載していません。
                </p>

                {{-- エラーレスポンス（実挙動に一致） --}}
                <h3 class="text-base font-black text-black mb-3 tracking-tight">エラーレスポンス</h3>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="text-left font-black px-4 py-3 w-24">ステータス</th>
                                <th class="text-left font-black px-4 py-3">意味</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">401</code></td>
                                <td class="px-4 py-3 text-gray-700 font-medium">APIキー未指定 / 無効</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">422</code></td>
                                <td class="px-4 py-3 text-gray-700 font-medium">パラメータ不正（class / direction が未定義値 等）</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">404</code></td>
                                <td class="px-4 py-3 text-gray-700 font-medium">対象が存在しない（存在しない model_id 等）</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">429</code></td>
                                <td class="px-4 py-3 text-gray-700 font-medium">レート制限超過（10/分・100/日）</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed mb-2">エラー時は次の形式のJSONを返します（例は 422）。</p>
                <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs overflow-x-auto mb-2"><code>{
  <span style="color:#7dd3fc">"error"</span>: <span style="color:#86efac">"invalid_class"</span>,
  <span style="color:#7dd3fc">"message"</span>: <span style="color:#86efac">"class は 50 / 125 / 250 / 400 / middle / large のいずれかを指定してください。"</span>,
  <span style="color:#7dd3fc">"allowed"</span>: [<span style="color:#fcd34d">50</span>, <span style="color:#fcd34d">125</span>, <span style="color:#fcd34d">250</span>, <span style="color:#fcd34d">400</span>, <span style="color:#86efac">"middle"</span>, <span style="color:#86efac">"large"</span>]
}</code></pre>
                <p class="text-xs text-gray-500 leading-relaxed mb-10">※429（レート制限超過）は <code class="text-gray-700">{"message":"Too Many Attempts."}</code> を返し、<code class="text-gray-700">Retry-After</code> ヘッダを付与します。</p>

                {{-- パラメータ早見表 --}}
                <h3 class="text-base font-black text-black mb-3 tracking-tight">パラメータ早見表</h3>
                <div class="overflow-x-auto mb-12">
                    <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="text-left font-black px-4 py-3">エンドポイント</th>
                                <th class="text-left font-black px-4 py-3">パラメータ</th>
                                <th class="text-left font-black px-4 py-3">指定できる値</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded text-xs">/rankings/listings</code></td>
                                <td class="px-4 py-3 font-bold text-gray-700">class</td>
                                <td class="px-4 py-3 text-gray-600">@foreach($classRows as $i => $row){{ $i ? ' / ' : '' }}{{ $row['class'] }}（{{ $row['cc'] }}）@endforeach</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded text-xs">/rankings/price-trends</code></td>
                                <td class="px-4 py-3 font-bold text-gray-700">direction</td>
                                <td class="px-4 py-3 text-gray-600">down（値下がり） / up（高騰）</td>
                            </tr>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded text-xs">/models/{model_id}/stats</code></td>
                                <td class="px-4 py-3 font-bold text-gray-700">model_id</td>
                                <td class="px-4 py-3 text-gray-600">車種ID（数値）</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- ① 流通台数ランキング --}}
                <div class="border-t border-gray-100 pt-8 mt-2">
                    <h3 class="text-base font-black text-black mb-2 tracking-tight">① 流通台数ランキング</h3>
                    <p class="text-sm text-gray-700 font-medium leading-relaxed mb-6">
                        現在MotoHubに掲載中の中古バイク在庫を、排気量クラス別の台数ランキングで取得できます。指標は「いま市場に出ている台数」（売れた台数ではありません）。
                    </p>

                    {{-- エンドポイント --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">エンドポイント</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">GET</span> /api/v1/rankings/listings<span style="color:#94a3b8">?</span><span style="color:#7dd3fc">class</span><span style="color:#94a3b8">=</span><span style="color:#fcd34d">{クラス}</span></code></pre>

                    {{-- クラス一覧（RANGESから動的生成） --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">クラス一覧（6種）</h4>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="text-left font-black px-4 py-3">class値</th>
                                    <th class="text-left font-black px-4 py-3">内容</th>
                                    <th class="text-left font-black px-4 py-3">排気量</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($classRows as $row)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">{{ $row['class'] }}</code></td>
                                    <td class="px-4 py-3 font-bold text-gray-700">{{ $row['content'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['cc'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- curl実行例 --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">curl 実行例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">curl</span> -H <span style="color:#86efac">"X-API-Key: YOUR_API_KEY"</span> \
  <span style="color:#86efac">"https://motohub.jp/api/v1/rankings/listings?class=250"</span></code></pre>

                    {{-- レスポンス例（実際の出力に合わせる） --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">レスポンス例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs overflow-x-auto mb-3"><code>{
  <span style="color:#7dd3fc">"class"</span>: <span style="color:#86efac">"250"</span>,
  <span style="color:#7dd3fc">"updated_at"</span>: <span style="color:#86efac">"2026-06-27T09:00:00+09:00"</span>,
  <span style="color:#7dd3fc">"source"</span>: <span style="color:#86efac">"MotoHub (motohub.jp)"</span>,
  <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">20</span>,
  <span style="color:#7dd3fc">"rankings"</span>: [
    {
      <span style="color:#7dd3fc">"rank"</span>: <span style="color:#fcd34d">1</span>,
      <span style="color:#7dd3fc">"model"</span>: <span style="color:#86efac">"レブル250"</span>,
      <span style="color:#7dd3fc">"maker"</span>: <span style="color:#86efac">"ホンダ"</span>,
      <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">1009</span>,
      <span style="color:#7dd3fc">"avg_price_man"</span>: <span style="color:#fcd34d">59</span>
    }
  ]
}</code></pre>
                    <ul class="text-xs text-gray-500 leading-relaxed mb-4 space-y-1">
                        <li><code class="text-gray-700">rank</code>：順位 / <code class="text-gray-700">model</code>：車種名 / <code class="text-gray-700">maker</code>：メーカー</li>
                        <li><code class="text-gray-700">count</code>：流通台数（現在掲載中の在庫台数） / <code class="text-gray-700">avg_price_man</code>：平均価格（万円）</li>
                    </ul>
                </div>

                {{-- 相場推移ランキングAPI（値下がり/高騰） --}}
                <div class="border-t border-gray-100 pt-8 mt-10">
                    <h3 class="text-base font-black text-black mb-2 tracking-tight">相場推移ランキング（値下がり／高騰）</h3>
                    <p class="text-sm text-gray-700 font-medium leading-relaxed mb-2">
                        車種別の<strong class="text-black">相場推移（値下がり・高騰）ランキング</strong>を取得できます。現在の平均価格・過去の平均価格・変化額・変化率・対象期間を返します。
                    </p>
                    <p class="text-xs text-gray-500 leading-relaxed mb-6 bg-blue-50/50 border border-blue-100 rounded-xl p-3">
                        比較期間：<strong class="text-gray-700">最新の日次平均価格</strong> vs <strong class="text-gray-700">約30日前の日次平均価格</strong>。並び順は「変化額」が大きい順です（変化率順ではありません）。
                    </p>

                    {{-- エンドポイント --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">エンドポイント</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">GET</span> /api/v1/rankings/price-trends<span style="color:#94a3b8">?</span><span style="color:#7dd3fc">direction</span><span style="color:#94a3b8">=</span><span style="color:#fcd34d">{down|up}</span></code></pre>

                    {{-- パラメータ --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">パラメータ</h4>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="text-left font-black px-4 py-3">direction</th>
                                    <th class="text-left font-black px-4 py-3">内容</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">down</code></td>
                                    <td class="px-4 py-3 font-bold text-gray-700">値下がりランキング</td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">up</code></td>
                                    <td class="px-4 py-3 font-bold text-gray-700">高騰ランキング</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- curl実行例 --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">curl 実行例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">curl</span> -H <span style="color:#86efac">"X-API-Key: YOUR_API_KEY"</span> \
  <span style="color:#86efac">"https://motohub.jp/api/v1/rankings/price-trends?direction=down"</span></code></pre>

                    {{-- レスポンス例（実際の出力に合わせる） --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">レスポンス例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs overflow-x-auto mb-3"><code>{
  <span style="color:#7dd3fc">"direction"</span>: <span style="color:#86efac">"down"</span>,
  <span style="color:#7dd3fc">"period"</span>: { <span style="color:#7dd3fc">"from"</span>: <span style="color:#86efac">"2026-05-28"</span>, <span style="color:#7dd3fc">"to"</span>: <span style="color:#86efac">"2026-06-27"</span>, <span style="color:#7dd3fc">"days"</span>: <span style="color:#fcd34d">30</span> },
  <span style="color:#7dd3fc">"updated_at"</span>: <span style="color:#86efac">"2026-06-27T09:00:00+09:00"</span>,
  <span style="color:#7dd3fc">"source"</span>: <span style="color:#86efac">"MotoHub (motohub.jp)"</span>,
  <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">30</span>,
  <span style="color:#7dd3fc">"rankings"</span>: [
    {
      <span style="color:#7dd3fc">"rank"</span>: <span style="color:#fcd34d">1</span>,
      <span style="color:#7dd3fc">"model"</span>: <span style="color:#86efac">"車種名"</span>,
      <span style="color:#7dd3fc">"maker"</span>: <span style="color:#86efac">"メーカー名"</span>,
      <span style="color:#7dd3fc">"current_price_man"</span>: <span style="color:#fcd34d">403.7</span>,
      <span style="color:#7dd3fc">"past_price_man"</span>: <span style="color:#fcd34d">916.7</span>,
      <span style="color:#7dd3fc">"diff_man"</span>: <span style="color:#fcd34d">-513.0</span>,
      <span style="color:#7dd3fc">"rate_pct"</span>: <span style="color:#fcd34d">-56.0</span>,
      <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">5</span>
    }
  ]
}</code></pre>
                    <ul class="text-xs text-gray-500 leading-relaxed mb-4 space-y-1">
                        <li><code class="text-gray-700">current_price_man</code> / <code class="text-gray-700">past_price_man</code>：現在・過去の平均価格（万円）</li>
                        <li><code class="text-gray-700">diff_man</code>：変化額（万円・マイナス＝値下がり） / <code class="text-gray-700">rate_pct</code>：変化率（％） / <code class="text-gray-700">count</code>：掲載台数</li>
                    </ul>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        ※認証・レート制限・データの鮮度・エラー仕様は、ページ上部の「共通仕様」を参照してください。
                    </p>
                </div>

                {{-- 車種別 市場データAPI --}}
                <div class="border-t border-gray-100 pt-8 mt-10">
                    <h3 class="text-base font-black text-black mb-2 tracking-tight">車種別 市場データ</h3>
                    <p class="text-sm text-gray-700 font-medium leading-relaxed mb-2">
                        車種ごとの<strong class="text-black">売れている地域・走行距離帯・年式・価格帯</strong>に加え、<strong class="text-black">先月の販売台数・平均在庫日数・6ヶ月の販売推移</strong>を1レスポンスで取得できます。
                    </p>
                    <p class="text-xs text-gray-500 leading-relaxed mb-6 bg-blue-50/50 border border-blue-100 rounded-xl p-3">
                        集計対象：<strong class="text-gray-700">直近3ヶ月の成約データ</strong>に基づきます。サイトの車種分析ページと同じ集計のため、数字は一致します。
                    </p>

                    {{-- エンドポイント --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">エンドポイント</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">GET</span> /api/v1/models/<span style="color:#fcd34d">{model_id}</span>/stats</code></pre>

                    {{-- パラメータ --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">パラメータ</h4>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm border border-gray-100 rounded-2xl overflow-hidden">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="text-left font-black px-4 py-3">パラメータ</th>
                                    <th class="text-left font-black px-4 py-3">内容</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3"><code class="bg-gray-100 text-blue-600 font-bold px-2 py-0.5 rounded">model_id</code></td>
                                    <td class="px-4 py-3 font-bold text-gray-700">車種ID（数値）</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- curl実行例 --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">curl 実行例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs sm:text-sm overflow-x-auto mb-6"><code><span style="color:#34d399;font-weight:700">curl</span> -H <span style="color:#86efac">"X-API-Key: YOUR_API_KEY"</span> \
  <span style="color:#86efac">"https://motohub.jp/api/v1/models/1238/stats"</span></code></pre>

                    {{-- レスポンス例 --}}
                    <h4 class="text-xs font-black text-black mb-2 uppercase tracking-widest">レスポンス例</h4>
                    <pre style="background:#0f172a;color:#e2e8f0" class="rounded-2xl p-4 text-xs overflow-x-auto mb-3"><code>{
  <span style="color:#7dd3fc">"model"</span>: <span style="color:#86efac">"YZF-R25"</span>,
  <span style="color:#7dd3fc">"maker"</span>: <span style="color:#86efac">"ヤマハ"</span>,
  <span style="color:#7dd3fc">"period"</span>: { <span style="color:#7dd3fc">"from"</span>: <span style="color:#86efac">"2026-03-28"</span>, <span style="color:#7dd3fc">"to"</span>: <span style="color:#86efac">"2026-06-27"</span>, <span style="color:#7dd3fc">"months"</span>: <span style="color:#fcd34d">3</span> },
  <span style="color:#7dd3fc">"updated_at"</span>: <span style="color:#86efac">"2026-06-27T09:00:00+09:00"</span>,
  <span style="color:#7dd3fc">"source"</span>: <span style="color:#86efac">"MotoHub (motohub.jp)"</span>,
  <span style="color:#7dd3fc">"summary"</span>: {
    <span style="color:#7dd3fc">"last_month_sold"</span>: <span style="color:#fcd34d">136</span>,
    <span style="color:#7dd3fc">"market_rank"</span>: <span style="color:#fcd34d">31</span>,
    <span style="color:#7dd3fc">"market_total"</span>: <span style="color:#fcd34d">2069</span>,
    <span style="color:#7dd3fc">"avg_stock_days"</span>: <span style="color:#fcd34d">54</span>
  },
  <span style="color:#7dd3fc">"regions"</span>: [
    { <span style="color:#7dd3fc">"rank"</span>: <span style="color:#fcd34d">1</span>, <span style="color:#7dd3fc">"prefecture"</span>: <span style="color:#86efac">"大阪府"</span>, <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">120</span> }
  ],
  <span style="color:#7dd3fc">"mileage_ranges"</span>: [
    { <span style="color:#7dd3fc">"range"</span>: <span style="color:#86efac">"〜5,000km"</span>, <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">220</span> }
  ],
  <span style="color:#7dd3fc">"years"</span>: [
    { <span style="color:#7dd3fc">"year"</span>: <span style="color:#fcd34d">2026</span>, <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">63</span> }
  ],
  <span style="color:#7dd3fc">"price_ranges"</span>: [
    { <span style="color:#7dd3fc">"range"</span>: <span style="color:#86efac">"50〜70万円"</span>, <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">268</span> }
  ],
  <span style="color:#7dd3fc">"monthly_sales"</span>: [
    { <span style="color:#7dd3fc">"month"</span>: <span style="color:#86efac">"2026-01"</span>, <span style="color:#7dd3fc">"count"</span>: <span style="color:#fcd34d">0</span> }
  ]
}</code></pre>
                    <ul class="text-xs text-gray-500 leading-relaxed mb-4 space-y-1">
                        <li><code class="text-gray-700">regions</code>：売れている地域TOP10 / <code class="text-gray-700">mileage_ranges</code>：走行距離帯 / <code class="text-gray-700">years</code>：年式</li>
                        <li><code class="text-gray-700">price_ranges</code>：価格帯 / <code class="text-gray-700">monthly_sales</code>：直近6ヶ月の販売推移</li>
                        <li><code class="text-gray-700">summary</code>：先月販売台数 / 市場順位 / 平均在庫日数</li>
                    </ul>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        ※認証・レート制限・エラー仕様は、ページ上部の「共通仕様」を参照してください。
                    </p>
                </div>
            </section>

            {{-- 4. 利用について --}}
            <section class="mb-16">
                <h2 class="text-xl font-black text-black mb-6 tracking-tight">利用について</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5">
                        <p class="text-xs font-black text-gray-400 uppercase tracking-wider mb-1">費用</p>
                        <p class="text-sm text-gray-700 font-bold">現在無料でご利用いただけます。</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5">
                        <p class="text-xs font-black text-gray-400 uppercase tracking-wider mb-1">クレジット</p>
                        <p class="text-sm text-gray-700 font-bold">出典「MotoHub」の明記をお願いします。</p>
                    </div>
                </div>

                {{-- 申し込み導線：既存の問い合わせフォームへ（種別=API利用 を事前選択） --}}
                <div class="bg-black rounded-2xl p-6 sm:p-8 text-center mb-6">
                    <p class="text-white text-sm font-bold mb-1">ご利用のお申し込み・APIキー発行のご相談</p>
                    <p class="text-gray-400 text-xs font-medium mb-5">お問い合わせ種別で「API利用・データ提供について」を選んでください。</p>
                    <a href="{{ route('pages.contact', ['category' => 'api']) }}"
                        class="inline-flex items-center gap-2 bg-white text-black px-8 py-4 rounded-xl text-sm font-black hover:bg-gray-100 transition active:scale-95">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                        お問い合わせフォームへ
                    </a>
                </div>

                {{-- 技術者向け詳細解説（Zenn） --}}
                <a href="https://zenn.dev/ausssxi" target="_blank" rel="noopener noreferrer"
                    class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-2xl p-5 hover:border-blue-300 transition-colors group">
                    <i data-lucide="file-text" class="w-5 h-5 text-blue-500 flex-shrink-0"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors">技術者向けの詳細解説（Zenn）</p>
                        <p class="text-xs text-gray-400 font-medium">APIの使い方・データの作り方をZennで解説しています。</p>
                    </div>
                    <i data-lucide="external-link" class="w-4 h-4 text-gray-300 flex-shrink-0"></i>
                </a>
            </section>

            {{-- 5. フッター / 補足 --}}
            <section class="border-t border-gray-100 pt-8">
                <h2 class="text-base font-black text-black mb-3 tracking-tight">今後について</h2>
                <p class="text-sm text-gray-500 font-medium leading-relaxed mb-2">
                    今後は、相場推移など他のデータも順次API化していく予定です。
                </p>
                <p class="text-sm text-gray-500 font-medium leading-relaxed">
                    「こんなデータはある?」というご相談も歓迎します。お気軽にお問い合わせフォームよりご連絡ください。
                </p>
            </section>

        </div>
    </div>
</x-layout>
