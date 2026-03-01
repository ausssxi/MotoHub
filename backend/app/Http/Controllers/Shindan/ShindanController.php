<?php

namespace App\Http\Controllers\Shindan;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShindanController extends Controller
{
    /**
     * 診断トップページ
     */
    public function index()
    {
        $categories = Category::orderBy('id')->get();
        return view('shindan.index', compact('categories'));
    }

    /**
     * 診断ロジック（AJAX）
     */
    public function diagnose(Request $request)
    {
        $validated = $request->validate([
            'displacement' => 'required|integer|min:50|max:2000',
            'usage'        => 'required|string|in:touring,commute,leisure,circuit',
            'budget'       => 'required|integer|min:5|max:500',
            'style'        => 'required|string|in:sporty,classic,wild,stylish',
            'priority'     => 'required|string|in:speed,fuel,looks,cargo,comfort',
        ]);

        // ここで呼び出している関数が、下の private function 欄に必要です
        $riderType = $this->determineRiderType($validated);
        $categoryIds = $this->getCategoryMapping($validated['usage'], $validated['style']);
        $bikes = $this->findMatchingBikes($validated, $categoryIds);

        $results = $bikes->take(3)->map(function ($bike) {
            $stats = DB::table('listings')
                ->where('bike_model_id', $bike->id)
                ->selectRaw('
                    COUNT(*) as count,
                    ROUND(AVG(total_price) / 10000) as avg_price,
                    ROUND(MIN(total_price) / 10000) as min_price,
                    ROUND(MAX(total_price) / 10000) as max_price
                ')
                ->first();

            return [
                'id'             => $bike->id,
                'name'           => $bike->name,
                'manufacturer'   => $bike->manufacturer?->name,
                'category'       => $bike->categoryData?->name, // category -> categoryData に修正済み
                'displacement'   => $bike->displacement,
                'image_url'      => $bike->image_url,
                'listings_count' => $bike->listings_count,
                'avg_price'      => $stats->avg_price ?? null,
                'min_price'      => $stats->min_price ?? null,
                'max_price'      => $stats->max_price ?? null,
            ];
        });

        return response()->json([
            'rider_type' => $riderType,
            'bikes'      => $results->values(),
        ]);
    }

    /**
     * シェア用結果ページ（OGP対応・3台表示版）
     */
    public function result(Request $request)
    {
        $type = $request->query('type', 'explorer');
        
        // 古い 'bike' パラメータの互換性処理を削除し、'bikes' のみを受け取る
        $bikesParam = $request->query('bikes', '');
        
        // カンマ区切りの文字列を数値の配列にし、空の要素を完全に排除する
        $bikeIds = array_filter(array_map('intval', explode(',', $bikesParam)));
        
        $riderType = $this->getRiderTypeByKey($type);
        
        // デフォルトは空のコレクション
        $bikes = collect(); 

        if (!empty($bikeIds)) {
            $idsString = implode(',', $bikeIds);
            $bikes = BikeModel::with(['manufacturer', 'categoryData'])
                ->whereIn('id', $bikeIds)
                ->orderByRaw("FIELD(id, $idsString)")
                ->get();
        }

        // テンプレートに渡す
        return view('shindan.result', compact('riderType', 'bikes'));
    }

    // ========================================
    // Private: ライダータイプ判定
    // ========================================

    private function determineRiderType(array $data): array
    {
        $types = $this->getAllRiderTypes();
        $scores = array_fill_keys(array_keys($types), 0);

        // 用途スコア
        match ($data['usage']) {
            'touring' => $this->addScores($scores, ['explorer' => 3, 'wild_cruiser' => 1]),
            'commute' => $this->addScores($scores, ['city_rider' => 2, 'practical_master' => 2]),
            'leisure' => $this->addScores($scores, ['city_rider' => 2, 'classic_soul' => 1]),
            'circuit' => $this->addScores($scores, ['speed_hunter' => 3]),
        };

        // スタイルスコア
        match ($data['style']) {
            'sporty'  => $this->addScores($scores, ['speed_hunter' => 2]),
            'classic' => $this->addScores($scores, ['classic_soul' => 3]),
            'wild'    => $this->addScores($scores, ['wild_cruiser' => 3]),
            'stylish' => $this->addScores($scores, ['city_rider' => 2, 'classic_soul' => 1]),
        };

        // 排気量スコア
        if ($data['displacement'] >= 700) {
            $this->addScores($scores, ['wild_cruiser' => 1, 'explorer' => 1]);
        } elseif ($data['displacement'] <= 250) {
            $this->addScores($scores, ['city_rider' => 1, 'practical_master' => 1]);
        }

        // 優先度スコア
        match ($data['priority']) {
            'speed'   => $this->addScores($scores, ['speed_hunter' => 2]),
            'fuel'    => $this->addScores($scores, ['practical_master' => 3]),
            'looks'   => $this->addScores($scores, ['classic_soul' => 1, 'city_rider' => 1]),
            'cargo'   => $this->addScores($scores, ['explorer' => 2, 'practical_master' => 1]),
            'comfort' => $this->addScores($scores, ['explorer' => 1, 'wild_cruiser' => 1]),
        };

        arsort($scores);
        $topKey = array_key_first($scores);
        return array_merge($types[$topKey], ['key' => $topKey, 'score' => $scores[$topKey]]);
    }

    private function addScores(array &$scores, array $additions): void
    {
        foreach ($additions as $key => $value) {
            $scores[$key] += $value;
        }
    }

    private function getAllRiderTypes(): array
    {
        return [
            'speed_hunter'     => ['name' => 'スピードハンター',     'emoji' => '⚡', 'desc' => 'スピードと走りの快感を追求するあなた。ワインディングやサーキットで真価を発揮する走り屋タイプ。', 'color' => '#EF4444', 'bg' => 'from-red-500 to-orange-500'],
            'explorer'         => ['name' => '旅するエクスプローラー', 'emoji' => '🗺️', 'desc' => '知らない道の先にある景色を求めるあなた。長距離ツーリングが最高の週末。',               'color' => '#3B82F6', 'bg' => 'from-blue-500 to-cyan-500'],
            'city_rider'       => ['name' => 'アーバンライダー',     'emoji' => '🌆', 'desc' => '街を自由に駆け抜けるスタイリッシュなあなた。通勤も休日も、バイクが日常の相棒。',        'color' => '#8B5CF6', 'bg' => 'from-purple-500 to-indigo-500'],
            'classic_soul'     => ['name' => 'クラシックソウル',     'emoji' => '🔧', 'desc' => 'バイクの歴史と美しさに惹かれるあなた。メカニカルな魅力とクラフトマンシップを愛する。', 'color' => '#D97706', 'bg' => 'from-amber-500 to-yellow-600'],
            'wild_cruiser'     => ['name' => 'ワイルドクルーザー',   'emoji' => '🦅', 'desc' => '大地を悠々と走る自由人。大排気量の鼓動と風を全身で感じるのが至福の時間。',             'color' => '#059669', 'bg' => 'from-emerald-600 to-teal-500'],
            'practical_master' => ['name' => '実用マスター',         'emoji' => '🎯', 'desc' => '燃費・維持費・使い勝手を完璧に計算するあなた。コスパ最強の選択ができる賢いライダー。', 'color' => '#6B7280', 'bg' => 'from-gray-600 to-gray-500'],
        ];
    }

    private function getRiderTypeByKey(string $key): array
    {
        $types = $this->getAllRiderTypes();
        $type = $types[$key] ?? $types['explorer'];
        $type['key'] = $key;
        return $type;
    }

    private function getCategoryMapping(string $usage, string $style): array
    {
        $map = [];
        if ($style === 'sporty')  $map = array_merge($map, [8, 20]);
        if ($style === 'classic') $map = array_merge($map, [4, 21, 7]);
        if ($style === 'wild')    $map = array_merge($map, [10, 14, 11]);
        if ($style === 'stylish') $map = array_merge($map, [4, 5, 20]);

        if ($usage === 'touring')  $map = array_merge($map, [6, 14]);
        if ($usage === 'circuit')  $map = array_merge($map, [8, 13]);
        if ($usage === 'commute')  $map = array_merge($map, [3, 19, 18, 1]);
        if ($usage === 'leisure')  $map = array_merge($map, [4, 5, 21, 12]);

        return array_unique($map);
    }

    private function findMatchingBikes(array $data, array $categoryIds)
    {
        $dispMin = (int)($data['displacement'] * 0.7);
        $dispMax = (int)($data['displacement'] * 1.3);
        $budgetMax = $data['budget'];

        // 第1検索: 全条件マッチ
        $bikes = BikeModel::query()
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->whereBetween('displacement', [$dispMin, $dispMax])
            ->when(!empty($categoryIds), fn($q) => $q->whereIn('category_id', $categoryIds))
            ->whereHas('listings', fn($q) => $q->where('is_sold_out', false)->where('total_price', '<=', $budgetMax * 10000))
            ->orderByDesc('listings_count')
            ->with(['manufacturer', 'categoryData']) // 修正済み
            ->limit(10)
            ->get();

        if ($bikes->count() >= 3) return $bikes;

        // 第2検索: 排気量範囲を広げる
        $bikes = BikeModel::query()
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->whereBetween('displacement', [(int)($dispMin * 0.5), (int)($dispMax * 1.5)])
            ->when(!empty($categoryIds), fn($q) => $q->whereIn('category_id', $categoryIds))
            ->orderByDesc('listings_count')
            ->with(['manufacturer', 'categoryData']) // 修正済み
            ->limit(10)
            ->get();

        if ($bikes->count() >= 3) return $bikes;

        // 第3検索: カテゴリ制約も外す（人気順）
        return BikeModel::query()
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->orderByDesc('listings_count')
            ->with(['manufacturer', 'categoryData']) // 修正済み
            ->limit(10)
            ->get();
    }
}