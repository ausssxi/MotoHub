<?php

namespace App\Http\Controllers\License;

use App\Http\Controllers\Controller;
use App\Models\DrivingSchool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * バイク免許ガイド（/license）
 *
 * 制度情報の出典（すべて公式・準公式の一次情報で確認済み / 2026年7月時点）:
 *  - 新基準原付            : 一般社団法人日本自動車工業会 https://www.jama.or.jp/operation/motorcycle/cat1_scooter/
 *  - 高速道路の通行・二人乗り: NEXCO西日本 https://www.w-nexco.co.jp/faq/09/motorcycle/
 *  - 二人乗りの条件・罰則   : 日本二輪車普及安全協会 https://www.jmpsa.or.jp/block/hokkaido/society/e15186.html
 *  - 試験・交付手数料（東京）: 警視庁 https://www.keishicho.metro.tokyo.lg.jp/menkyo/menkyo/annai/intro/tetsuzuki00.html
 *
 * ★未確認スロット（公開前に要確認。確認できるまで画面には出さない）:
 *  - 'age' … 受験資格年齢。警視庁の手数料ページには二輪の年齢記載なし。
 *  - 'school_cost' … 教習所費用。公的統計が存在しないため、実在教習所の公式料金表で確認したものだけを入れる。
 */
class LicenseController extends Controller
{
    /** 在庫統計のキャッシュ時間（秒） */
    private const CACHE_TTL = 3600;

    public const CLASSES = [
        'gentsuki' => [
            'key'          => 'gentsuki',
            'name'         => '原付一種',
            'formal'       => '原動機付自転車免許',
            'emoji'        => '🛵',
            'range_label'  => '〜50cc',
            'cc_min'       => 1,
            'cc_max'       => 50,
            'search_query' => ['displacement_max' => 50],
            'highway'      => false,
            'tandem'       => false,
            'speed_limit'  => '法定30km/h',
            'two_stage'    => true,
            'fees'         => ['試験手数料' => 1600, '免許証交付手数料' => 2350, '原付講習受講料' => 5250],
            'age'          => null,          // ★未確認
            'school_cost'  => null,          // ★未確認
            'summary'      => '原付免許で乗れる、いちばん手前の入口。学科試験と原付講習だけで取得でき、費用も最小。ただし法定速度30km/h・二段階右折・二人乗り禁止・高速道路不可という制約がある。',
            'notes'        => [
                '2025年4月1日から「新基準原付」が加わった。排気量125cc以下かつ最高出力4.0kW以下に制御された二輪車が原付一種として扱われ、原付免許で運転できる。',
                '新基準原付でも交通ルールは従来の原付と同じ（最高速度30km/h、二段階右折、二人乗り禁止、高速道路・自動車専用道路の通行禁止、最大積載重量30kg）。',
                '新基準原付のナンバープレートは白色。軽自動車税は従来と同額の年2,000円。',
                '従来の50cc以下の原付が廃止されたわけではなく、引き続き運転できる。売買の制限もない。',
                '50cc以下のバイクでの二人乗りは違反。反則点数1点・反則金5,000円。',
            ],
        ],
        'kogata' => [
            'key'          => 'kogata',
            'name'         => '原付二種（小型限定）',
            'formal'       => '普通自動二輪車免許（小型限定）',
            'emoji'        => '🛴',
            'range_label'  => '51〜125cc',
            'cc_min'       => 51,
            'cc_max'       => 125,
            'search_query' => ['displacement_min' => 51, 'displacement_max' => 125],
            'highway'      => false,
            'tandem'       => true,
            'speed_limit'  => '一般道の標識に従う（30km/h制限なし）',
            'two_stage'    => false,
            'fees'         => ['試験手数料' => 4550, '免許証交付手数料' => 2350],
            'age'          => null,          // ★未確認
            'school_cost'  => null,          // ★未確認
            'summary'      => '原付の制約から解放される、いちばん費用対効果の高い区分。30km/h制限と二段階右折がなくなり、二人乗りもできる。高速道路だけは走れない。通勤・街乗りの最適解と言われる理由がここにある。',
            'notes'        => [
                '二人乗りができるのは、免許（小型限定・普通二輪・大型二輪）を取得してから1年以上経過し、かつ車両に乗車定員2名と記載がある場合。',
                '125cc以下のため高速道路・自動車専用道路は通行できない。',
                '最高出力4.0kW以下に制御された125cc以下のモデルは「新基準原付」として原付一種扱いになるため、購入時は車両の区分表示を確認する。',
            ],
        ],
        'futsuu' => [
            'key'          => 'futsuu',
            'name'         => '普通二輪',
            'formal'       => '普通自動二輪車免許',
            'emoji'        => '🏍️',
            'range_label'  => '126〜400cc',
            'cc_min'       => 126,
            'cc_max'       => 400,
            'search_query' => ['displacement_min' => 126, 'displacement_max' => 400],
            'highway'      => true,
            'tandem'       => true,
            'speed_limit'  => '一般道・高速道路の標識に従う',
            'two_stage'    => false,
            'fees'         => ['試験手数料' => 4550, '免許証交付手数料' => 2350],
            'age'          => null,          // ★未確認
            'school_cost'  => null,          // ★未確認
            'summary'      => '高速道路に乗れるようになる境目。125cc超から高速通行が可能になるため、ツーリングの行動範囲が一気に広がる。中古在庫もこの区分がいちばん厚い。',
            'notes'        => [
                '高速道路を通行できるのは125cc超の自動二輪車。この区分から可能になる。',
                '高速道路での二人乗りは、20歳以上かつ大型二輪免許または普通二輪免許を受けていた期間が通算3年以上であることが条件。',
                '一般道での二人乗りは免許取得から1年以上経過していることが条件。',
                '首都高速道路の一部区間など、自動二輪車の二人乗りが禁止されている区間がある。走行前に確認が必要。',
            ],
        ],
        'oogata' => [
            'key'          => 'oogata',
            'name'         => '大型二輪',
            'formal'       => '大型自動二輪車免許',
            'emoji'        => '🏁',
            'range_label'  => '401cc〜（排気量無制限）',
            'cc_min'       => 401,
            'cc_max'       => 9999,
            'search_query' => ['displacement_min' => 401],
            'highway'      => true,
            'tandem'       => true,
            'speed_limit'  => '一般道・高速道路の標識に従う',
            'two_stage'    => false,
            'fees'         => ['試験手数料' => 4550, '免許証交付手数料' => 2350],
            'age'          => null,          // ★未確認
            'school_cost'  => null,          // ★未確認
            'summary'      => '排気量の上限がなくなる最上位区分。ハーレー、BMW、ドゥカティといった輸入車を含め、選べる車種が最も多い。長距離ツーリングと二人乗りの快適さは別格。',
            'notes'        => [
                '排気量の制限がなくなり、すべての二輪車を運転できる。',
                '高速道路での二人乗りは、20歳以上かつ大型二輪免許または普通二輪免許を受けていた期間が通算3年以上であることが条件。',
                '車検が必要（新車は初回3年、以降2年ごと）。',
            ],
        ],
    ];

    /** 出典一覧（画面下部に表示） */
    public const SOURCES = [
        ['label' => '新基準原付について（一般社団法人日本自動車工業会）', 'url' => 'https://www.jama.or.jp/operation/motorcycle/cat1_scooter/'],
        ['label' => '自動二輪車のご利用について（NEXCO西日本）',           'url' => 'https://www.w-nexco.co.jp/faq/09/motorcycle/'],
        ['label' => 'バイクの2人乗りまとめ（日本二輪車普及安全協会）',      'url' => 'https://www.jmpsa.or.jp/block/hokkaido/society/e15186.html'],
        ['label' => '運転免許試験一覧（警視庁）',                          'url' => 'https://www.keishicho.metro.tokyo.lg.jp/menkyo/menkyo/annai/intro/tetsuzuki00.html'],
    ];

    /**
     * ハブページ：4区分の比較
     */
    public function index()
    {
        $classes = [];
        foreach (self::CLASSES as $key => $c) {
            $c['stats'] = $this->stats($c['cc_min'], $c['cc_max']);
            $c['url']   = route('license.show', $key);
            $classes[]  = $c;
        }

        $totalStock = array_sum(array_column(array_column($classes, 'stats'), 'stock'));

        // 教習所一覧への導線用（件数はDBの公開データから算出・ベタ書きしない）。
        $schoolCount = DrivingSchool::published()->nirin()->count();
        $schoolPrefectureCount = DrivingSchool::published()->nirin()->distinct()->count('prefecture_slug');

        return view('license.index', [
            'classes'    => $classes,
            'totalStock' => $totalStock,
            'sources'    => self::SOURCES,
            'schoolCount' => $schoolCount,
            'schoolPrefectureCount' => $schoolPrefectureCount,
        ]);
    }

    /**
     * 区分別の詳細ページ
     */
    public function show(string $class)
    {
        abort_unless(isset(self::CLASSES[$class]), 404);

        $c          = self::CLASSES[$class];
        $c['stats'] = $this->stats($c['cc_min'], $c['cc_max']);
        $c['models'] = $this->topModels($c['cc_min'], $c['cc_max']);

        // 前後の区分（ステップアップ導線）
        $keys  = array_keys(self::CLASSES);
        $i     = array_search($class, $keys, true);
        $prev  = $i > 0 ? self::CLASSES[$keys[$i - 1]] : null;
        $next  = $i < count($keys) - 1 ? self::CLASSES[$keys[$i + 1]] : null;

        return view('license.show', [
            'c'       => $c,
            'prev'    => $prev,
            'next'    => $next,
            'sources' => self::SOURCES,
            'all'     => self::CLASSES,
        ]);
    }

    /**
     * 排気量帯の在庫台数・平均価格・最安値
     */
    private function stats(int $min, int $max): array
    {
        return Cache::remember("license.stats.{$min}.{$max}", self::CACHE_TTL, function () use ($min, $max) {
            $row = DB::table('listings as l')
                ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
                ->where('l.is_sold_out', false)
                ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
                ->whereBetween('bm.displacement', [$min, $max])
                ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price, ROUND(MIN(l.total_price)) as min_price')
                ->first();

            return [
                'stock'     => (int) ($row->stock ?? 0),
                'avg_price' => (int) ($row->avg_price ?? 0),
                'min_price' => (int) ($row->min_price ?? 0),
            ];
        });
    }

    /**
     * 在庫の多い代表車種（slugが空のモデルは除外＝リンク切れ防止）
     */
    private function topModels(int $min, int $max, int $limit = 12): array
    {
        return Cache::remember("license.models.{$min}.{$max}.{$limit}", self::CACHE_TTL, function () use ($min, $max, $limit) {
            return DB::table('listings as l')
                ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
                ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
                ->where('l.is_sold_out', false)
                ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
                ->whereBetween('bm.displacement', [$min, $max])
                ->whereNotNull('bm.slug')->where('bm.slug', '!=', '')
                ->selectRaw('bm.slug, CONCAT(m.name, " ", bm.name) as name, bm.displacement, bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price, ROUND(MIN(l.total_price)) as min_price')
                ->groupByRaw('bm.id, bm.slug, bm.name, m.name, bm.displacement, bm.category')
                ->orderByDesc('stock')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
}
