<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class QuizQuestionGenerator
{
    /**
     * 指定カテゴリの問題を生成する
     */
    public function generate(string $category, int $count = 50): array
    {
        $method = match ($category) {
            'price'    => 'generatePrice',
            'ranking'  => 'generateRanking',
            'speed'    => 'generateSpeed',
            'region'   => 'generateRegional',
            default    => null,
        };

        if (!$method) {
            return [];
        }

        $questions = $this->$method($count);

        return $questions->values()->toArray();
    }

    /**
     * 中古価格クイズ: bike_model_market_stats から平均価格を出題
     */
    private function generatePrice(int $count): Collection
    {
        $models = DB::table('bike_model_market_stats')
            ->join('bike_models', 'bike_model_market_stats.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->where('bike_model_market_stats.avg_price', '>', 0)
            ->where('bike_model_market_stats.listing_count', '>=', 3)
            ->select(
                'manufacturers.name as mfr_name',
                'bike_models.name as model_name',
                'bike_model_market_stats.avg_price'
            )
            ->inRandomOrder()
            ->limit($count)
            ->get();

        return $models->map(function ($row) {
            $avgMan = (int) round($row->avg_price / 10000); // 円→万円
            return $this->buildPriceQuestion(
                "{$row->mfr_name} {$row->model_name}",
                $avgMan
            );
        })->filter();
    }

    /**
     * 売れ筋ランキングクイズ: is_sold_out=true の直近売却データで集計
     */
    private function generateRanking(int $count): Collection
    {
        $questions = collect();

        // 排気量帯ごとの売れ筋ランキング問題
        $displacementRanges = [
            ['label' => '原付二種(〜125cc)', 'min' => 0, 'max' => 125],
            ['label' => '250ccクラス', 'min' => 126, 'max' => 250],
            ['label' => '400ccクラス', 'min' => 251, 'max' => 400],
            ['label' => '大型バイク(401cc〜)', 'min' => 401, 'max' => 99999],
        ];

        foreach ($displacementRanges as $range) {
            $top = $this->getSoldRanking($range['min'], $range['max'], 8);
            if ($top->count() >= 4) {
                $questions->push($this->buildRankingQuestion(
                    "{$range['label']}で先月最も売れたバイクは？",
                    $top
                ));
            }
        }

        // メーカー別ランキング問題
        $manufacturers = DB::table('manufacturers')->pluck('name', 'id');
        foreach ($manufacturers->shuffle()->take(4) as $mfrId => $mfrName) {
            $top = DB::table('listings')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->where('listings.is_sold_out', true)
                ->where('listings.updated_at', '>=', now()->subMonth())
                ->where('bike_models.manufacturer_id', $mfrId)
                ->select('bike_models.name', DB::raw('COUNT(*) as sold_count'))
                ->groupBy('bike_models.name')
                ->orderByDesc('sold_count')
                ->limit(8)
                ->get();

            if ($top->count() >= 4) {
                $questions->push($this->buildRankingQuestion(
                    "{$mfrName}車で先月最も売れたのは？",
                    $top
                ));
            }
        }

        // 全体ランキング
        $topAll = $this->getSoldRanking(0, 99999, 8);
        if ($topAll->count() >= 4) {
            $questions->push($this->buildRankingQuestion(
                '先月の中古バイク売れ筋1位は？',
                $topAll
            ));
        }

        return $questions->shuffle()->take($count);
    }

    /**
     * 売却スピードクイズ: DATEDIFF(updated_at, created_at) で売却日数を近似
     */
    private function generateSpeed(int $count): Collection
    {
        $models = DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->where('listings.is_sold_out', true)
            ->where('listings.updated_at', '>=', now()->subMonths(3))
            ->whereRaw('DATEDIFF(listings.updated_at, listings.created_at) > 0')
            ->whereRaw('DATEDIFF(listings.updated_at, listings.created_at) < 180')
            ->select(
                'manufacturers.name as mfr_name',
                'bike_models.name as model_name',
                DB::raw('ROUND(AVG(DATEDIFF(listings.updated_at, listings.created_at))) as avg_days'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('bike_models.id', 'manufacturers.name', 'bike_models.name')
            ->having('cnt', '>=', 3)
            ->inRandomOrder()
            ->limit($count)
            ->get();

        return $models->map(function ($row) {
            $avgDays = (int) $row->avg_days;
            if ($avgDays <= 0) {
                return null;
            }
            return $this->buildSpeedQuestion(
                "{$row->mfr_name} {$row->model_name}",
                $avgDays
            );
        })->filter();
    }

    /**
     * 地域別人気クイズ: listings JOIN shops で shops.prefecture を使用
     */
    private function generateRegional(int $count): Collection
    {
        $questions = collect();

        // 都道府県ごとの人気バイク問題
        $prefectures = ['東京都', '大阪府', '愛知県', '北海道', '福岡県', '神奈川県', '埼玉県', '千葉県', '兵庫県', '広島県'];

        foreach (collect($prefectures)->shuffle()->take(6) as $pref) {
            $top = DB::table('listings')
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->where('shops.prefecture', $pref)
                ->where('listings.is_sold_out', false)
                ->select('bike_models.name', DB::raw('COUNT(*) as listing_count'))
                ->groupBy('bike_models.name')
                ->orderByDesc('listing_count')
                ->limit(8)
                ->get();

            if ($top->count() >= 4) {
                $questions->push($this->buildRankingQuestion(
                    "{$pref}で最も流通が多いバイクは？",
                    $top
                ));
            }
        }

        // 地域ごとの排気量帯人気
        $regions = [
            '関東' => ['東京都', '神奈川県', '埼玉県', '千葉県', '茨城県', '栃木県', '群馬県'],
            '関西' => ['大阪府', '京都府', '兵庫県', '奈良県', '滋賀県', '和歌山県'],
            '中部' => ['愛知県', '静岡県', '岐阜県', '三重県', '長野県'],
            '九州' => ['福岡県', '熊本県', '大分県', '宮崎県', '鹿児島県', '佐賀県', '長崎県'],
        ];

        foreach ($regions as $regionName => $prefs) {
            $top = DB::table('listings')
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
                ->whereIn('shops.prefecture', $prefs)
                ->where('listings.is_sold_out', false)
                ->select(
                    DB::raw("CONCAT(manufacturers.name, ' ', bike_models.name) as full_name"),
                    DB::raw('COUNT(*) as listing_count')
                )
                ->groupBy('full_name')
                ->orderByDesc('listing_count')
                ->limit(8)
                ->get();

            if ($top->count() >= 4) {
                $questions->push($this->buildRankingQuestion(
                    "{$regionName}で最も人気のバイクは？",
                    $top
                ));
            }
        }

        return $questions->shuffle()->take($count);
    }

    // ── Helper Methods ──

    /**
     * 売却ランキングを取得
     */
    private function getSoldRanking(int $minCc, int $maxCc, int $limit): Collection
    {
        return DB::table('listings')
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->where('listings.is_sold_out', true)
            ->where('listings.updated_at', '>=', now()->subMonth())
            ->where('bike_models.displacement', '>=', $minCc)
            ->where('bike_models.displacement', '<=', $maxCc)
            ->select('bike_models.name', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_models.name')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();
    }

    /**
     * 価格クイズの問題を組み立てる
     * 正解の前後にダミー選択肢を生成
     */
    private function buildPriceQuestion(string $bikeName, int $avgMan): ?array
    {
        if ($avgMan <= 0) {
            return null;
        }

        // 万円単位のダミー価格を生成（正解±20〜40%程度）
        $step = max(5, (int) round($avgMan * 0.15 / 5) * 5); // 5万円刻みに丸める
        $choices = [
            $avgMan - $step * 2,
            $avgMan - $step,
            $avgMan,
            $avgMan + $step,
        ];

        // 負の値を防止
        $choices = array_map(fn($v) => max(1, $v), $choices);

        // シャッフルして正解位置を追跡
        $answerValue = $avgMan;
        $indexed = array_map(fn($v, $i) => ['value' => $v, 'original' => $i], $choices, array_keys($choices));
        shuffle($indexed);

        $answerIdx = 0;
        $labels = [];
        foreach ($indexed as $i => $item) {
            $labels[] = "約{$item['value']}万円";
            if ($item['value'] === $answerValue) {
                $answerIdx = $i;
            }
        }

        return [
            'q' => "{$bikeName}の中古平均価格は？",
            'choices' => $labels,
            'answer' => $answerIdx,
        ];
    }

    /**
     * ランキング系の問題を組み立てる
     * 1位を正解、2〜4位からダミーを取る
     */
    private function buildRankingQuestion(string $questionText, Collection $ranking): ?array
    {
        if ($ranking->count() < 4) {
            return null;
        }

        $correct = $ranking->first()->name;
        $others = $ranking->slice(1)->pluck('name')->shuffle()->take(3)->values()->toArray();
        $allChoices = array_merge([$correct], $others);
        shuffle($allChoices);

        $answerIdx = array_search($correct, $allChoices);

        return [
            'q' => $questionText,
            'choices' => array_values($allChoices),
            'answer' => (int) $answerIdx,
        ];
    }

    /**
     * 売却スピードの問題を組み立てる
     */
    private function buildSpeedQuestion(string $bikeName, int $avgDays): array
    {
        // 日数に応じた選択肢を生成
        $step = max(3, (int) round($avgDays * 0.3));
        $choices = [
            max(1, $avgDays - $step * 2),
            max(2, $avgDays - $step),
            $avgDays,
            $avgDays + $step,
        ];

        // 重複を防ぐ
        $choices = array_unique($choices);
        while (count($choices) < 4) {
            $choices[] = $avgDays + $step * count($choices);
        }
        $choices = array_values($choices);

        $answerValue = $avgDays;
        $indexed = array_map(fn($v, $i) => ['value' => $v, 'original' => $i], $choices, array_keys($choices));
        shuffle($indexed);

        $answerIdx = 0;
        $labels = [];
        foreach ($indexed as $i => $item) {
            $labels[] = "約{$item['value']}日";
            if ($item['value'] === $answerValue) {
                $answerIdx = $i;
            }
        }

        return [
            'q' => "{$bikeName}の平均売却日数は？",
            'choices' => $labels,
            'answer' => $answerIdx,
        ];
    }
}
