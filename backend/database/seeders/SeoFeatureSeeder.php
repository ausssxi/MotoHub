<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeoFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'slug' => 'commute-125cc',
                'title' => '通勤・通学におすすめの125cc以下バイク',
                'meta_description' => '通勤・通学に最適な125cc以下の原付二種バイクを特集。燃費が良く維持費も安い、毎日使える実用的な1台を見つけましょう。全国の在庫から価格・走行距離を比較できます。',
                'content_header' => '<p>毎日の通勤・通学にバイクを使いたいなら、<strong>125cc以下の原付二種</strong>がおすすめです。普通自動車免許に小型限定を追加するだけで乗れるうえ、燃費は30〜50km/Lと経済的。車体価格も手頃で、任意保険はファミリーバイク特約が使えるため維持費を大幅に抑えられます。</p><p>通勤ラッシュの渋滞をすり抜け、駐輪場にもコンパクトに収まる125ccクラス。PCX、アドレス125、ジョグなど人気モデルの在庫をチェックして、快適な通勤ライフを始めましょう。</p>',
                'search_conditions' => json_encode(['min_displacement' => 50, 'max_displacement' => 125]),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'bargain_desc',
                'is_active' => true,
                'sort_order' => 10,
                'icon' => 'zap',
                'color' => 'bg-gradient-to-br from-blue-500 to-cyan-400',
            ],
            [
                'slug' => 'short-riders-250cc',
                'title' => '足つきが良い250cc以下バイク',
                'meta_description' => '足つきの良い250cc以下のバイクを特集。シート高が低めで初心者や小柄なライダーも安心して乗れるモデルを厳選。全国の中古・新車在庫を比較できます。',
                'content_header' => '<p>バイク選びで気になるのが「足つき」。特に初心者や小柄なライダーにとって、<strong>両足が地面にしっかり届くかどうか</strong>は安心感に直結します。250ccクラスなら車検不要で維持費もお手頃。</p><p>レブル250、ニンジャ250、PCXなどシート高が低めの人気モデルから、あなたの体格にぴったりの1台を見つけてください。中古なら足回りのカスタム済み車両も狙い目です。</p>',
                'search_conditions' => json_encode(['max_displacement' => 250]),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'latest',
                'is_active' => true,
                'sort_order' => 20,
                'icon' => 'user',
                'color' => 'bg-gradient-to-br from-green-500 to-emerald-400',
            ],
            [
                'slug' => 'large-under-100man',
                'title' => '100万円以下で買える大型バイク',
                'meta_description' => '支払総額100万円以下で手に入る401cc以上の大型バイクを特集。憧れのリッターバイクも中古ならお手頃価格。全国の在庫から価格比較できます。',
                'content_header' => '<p>「大型バイクに乗りたいけど、予算が心配…」そんなあなたに朗報です。中古市場なら<strong>支払総額100万円以下</strong>で手に入る大型バイクがたくさんあります。</p><p>CB1300SF、ZRX1200、V-Strom650など、人気の大型モデルが予算内で見つかるかも。高速道路でのゆとりある走り、長距離ツーリングの快適さは大型ならでは。価格と状態を比較して、コスパ最強の大型バイクを見つけましょう。</p>',
                'search_conditions' => json_encode(['max_price' => 100, 'min_displacement' => 401]),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'price_asc',
                'is_active' => true,
                'sort_order' => 30,
                'icon' => 'coins',
                'color' => 'bg-gradient-to-br from-amber-500 to-yellow-400',
            ],
            [
                'slug' => 'budget-under-10man',
                'title' => '10万円以下の格安バイク',
                'meta_description' => '支払総額10万円以下で買える格安バイクを特集。とにかく安くバイクに乗りたい方必見。全国の激安バイク在庫を一覧で比較。',
                'content_header' => '<p>とにかく安くバイクに乗りたい方必見！<strong>支払総額10万円以下</strong>の格安バイクを一挙掲載しています。</p><p>「初めてのバイクは安い車体で練習したい」「セカンドバイクとして近所の足が欲しい」という方にぴったり。走行距離や年式は多めですが、まだまだ現役で走れる掘り出し物が見つかるかもしれません。気になる車両があれば、早めにショップへ問い合わせましょう。</p>',
                'search_conditions' => json_encode(['max_price' => 10]),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'price_asc',
                'is_active' => true,
                'sort_order' => 40,
                'icon' => 'piggy-bank',
                'color' => 'bg-gradient-to-br from-rose-500 to-red-400',
            ],
            [
                'slug' => 'etc-equipped',
                'title' => 'ETC付きバイク特集',
                'meta_description' => 'ETC車載器付きのバイクを特集。高速道路の料金所をノンストップで通過できるETC搭載車を全国から検索。ツーリングに便利な1台を見つけましょう。',
                'content_header' => '<p>高速道路でのツーリングに欠かせない<strong>ETC車載器</strong>。料金所をノンストップで通過でき、二輪車定率割引などのお得な制度も利用可能です。</p><p>最初からETCが装備されたバイクなら、面倒な取り付け工賃もゼロ。納車後すぐにツーリングに出発できます。ロングツーリング派のあなたに最適な、ETC付きバイクをチェックしてみてください。</p>',
                'search_conditions' => json_encode(['tag' => 'ETC']),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'latest',
                'is_active' => true,
                'sort_order' => 50,
                'icon' => 'credit-card',
                'color' => 'bg-gradient-to-br from-indigo-500 to-blue-400',
            ],
            [
                'slug' => 'new-scooter',
                'title' => '新車で買えるスクーター特集',
                'meta_description' => '新車で購入できるスクーターを特集。メーカー保証付きの安心感と最新装備。通勤・通学・買い物に便利なスクーターの新車在庫を比較できます。',
                'content_header' => '<p>新車のスクーターなら<strong>メーカー保証が付いて安心</strong>。最新のアイドリングストップ機能やUSBソケットなど、便利装備も充実しています。</p><p>ATで操作がかんたん、メットインスペースに荷物が入る実用性の高さ。通勤・通学はもちろん、買い物やちょっとしたお出かけにも大活躍。PCX、NMAX、アドレスなど人気スクーターの新車在庫をチェックしましょう。</p>',
                'search_conditions' => json_encode(['is_new' => 1]),
                'keyword' => 'スクーター',
                'prefecture' => null,
                'sort' => 'price_asc',
                'is_active' => true,
                'sort_order' => 60,
                'icon' => 'sparkles',
                'color' => 'bg-gradient-to-br from-purple-500 to-pink-400',
            ],
            [
                'slug' => 'one-owner',
                'title' => 'ワンオーナー車のバイク特集',
                'meta_description' => '前オーナーが1人だけのワンオーナーバイクを特集。丁寧にメンテナンスされた良質な中古バイクを全国から検索できます。',
                'content_header' => '<p>中古バイク選びで「前のオーナーが大切に乗っていたか」は重要なポイント。<strong>ワンオーナー車</strong>は新車から1人のオーナーだけが乗ってきた車両で、メンテナンス履歴が追いやすく、状態が良い傾向にあります。</p><p>複数人の手を渡っていないぶん、消耗品の交換時期も把握しやすいのが魅力。少し予算を上げてでも、程度の良い1台を手に入れたい方におすすめです。</p>',
                'search_conditions' => json_encode(['tag' => 'ワンオーナー']),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'latest',
                'is_active' => true,
                'sort_order' => 70,
                'icon' => 'crown',
                'color' => 'bg-gradient-to-br from-yellow-500 to-orange-400',
            ],
            [
                'slug' => '250cc-no-inspection',
                'title' => '車検不要！維持費がお得な250ccバイク',
                'meta_description' => '車検不要で維持費を抑えられる126cc〜250ccクラスのバイクを特集。軽二輪は車検なしで高速道路も走れるコスパ最強クラス。',
                'content_header' => '<p>250ccクラス（126cc〜250cc）の最大の魅力は<strong>車検が不要</strong>なこと。2年ごとの車検費用（数万円〜）がかからないため、大幅に維持費を節約できます。</p><p>それでいて高速道路は問題なく走れるので、ツーリングの自由度は十分。ニンジャ250、CBR250RR、YZF-R25など、250ccスポーツモデルは走りの楽しさも一級品。通勤からツーリングまでオールマイティに活躍する250ccバイクを探してみましょう。</p>',
                'search_conditions' => json_encode(['min_displacement' => 126, 'max_displacement' => 250]),
                'keyword' => null,
                'prefecture' => null,
                'sort' => 'bargain_desc',
                'is_active' => true,
                'sort_order' => 80,
                'icon' => 'shield-check',
                'color' => 'bg-gradient-to-br from-teal-500 to-green-400',
            ],
        ];

        foreach ($features as $feature) {
            DB::table('seo_features')->updateOrInsert(
                ['slug' => $feature['slug']],
                $feature
            );
        }
    }
}
