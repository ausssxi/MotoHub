<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GenerateRankingNews extends Command
{
    protected $signature = 'news:generate-ranking
                            {--type=daily : daily|weekly|monthly}
                            {--force : 重複チェックをスキップ}';

    protected $description = 'ランキングデータからニュース記事を自動生成';

    public function handle(): int
    {
        $type = $this->option('type');

        if (!in_array($type, ['daily', 'weekly', 'monthly'], true)) {
            $this->error("無効なタイプ: {$type}（daily|weekly|monthly）");
            return self::FAILURE;
        }

        $this->info("ランキングニュース生成開始: {$type}");

        [$title, $startDate, $endDate, $limit, $periodLabel] = $this->resolveParams($type);

        // 重複チェック
        if (!$this->option('force') && BikeNews::where('title', $title)->exists()) {
            $this->warn("既に同じタイトルの記事が存在します: {$title}");
            return self::SUCCESS;
        }

        // 販売データ集計
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<', $endDate)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();

        if ($rankings->isEmpty()) {
            $this->warn('販売データがないためスキップ');
            return self::SUCCESS;
        }

        // 車種・メーカー情報を一括取得
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        // 平均価格を一括取得（今期）
        $avgPrices = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<', $endDate)
            ->whereIn('bike_model_id', $rankings->pluck('bike_model_id'))
            ->whereNotNull('total_price')
            ->select('bike_model_id', DB::raw('ROUND(AVG(total_price) / 10000, 1) as avg_price'))
            ->groupBy('bike_model_id')
            ->pluck('avg_price', 'bike_model_id');

        // 前期間の順位・平均価格を取得
        [$prevRanks, $prevAvgPrices] = $this->fetchPreviousPeriod($type, $startDate, $rankings->pluck('bike_model_id')->all());

        $this->info('前期間データ: ' . count($prevRanks) . '車種');

        // HTML本文生成
        $content = $this->buildContent($rankings, $models, $avgPrices, $prevRanks, $prevAvgPrices, $periodLabel);

        // サムネイル: 1位の車種画像
        $topModel = $models->get($rankings->first()->bike_model_id);
        $thumbnail = $this->resolveImageUrl($topModel);

        // 保存
        BikeNews::create([
            'title'           => $title,
            'url'             => route('ranking.index') . '#' . $type . '-' . now()->format('Ymd'),
            'source'          => 'MotoHub',
            'content'         => $content,
            'thumbnail_url'   => $thumbnail,
            'published_at'    => now(),
            'bike_model_id'   => $rankings->first()->bike_model_id,
            'manufacturer_id' => null,
            'is_featured'     => $type === 'monthly',
        ]);

        $this->info("記事を生成しました: {$title}");

        return self::SUCCESS;
    }

    private function resolveParams(string $type): array
    {
        return match ($type) {
            'daily' => [
                '【MotoHub調べ】' . Carbon::yesterday()->format('Y年n月j日') . ' バイク売れ筋デイリーランキングTOP10',
                Carbon::yesterday()->startOfDay(),
                Carbon::today()->startOfDay(),
                10,
                Carbon::yesterday()->format('Y年n月j日'),
            ],
            'weekly' => [
                '【MotoHub調べ】' . now()->format('Y年') . '第' . now()->subWeek()->weekOfYear . '週 バイク売れ筋ウィークリーランキングTOP10',
                now()->subWeek()->startOfWeek(),
                now()->startOfWeek(),
                10,
                now()->subWeek()->startOfWeek()->format('n/j') . '〜' . now()->subWeek()->endOfWeek()->format('n/j'),
            ],
            'monthly' => [
                '【MotoHub調べ】' . now()->subMonth()->format('Y年n月') . ' バイク売れ筋マンスリーランキングTOP20',
                now()->subMonth()->startOfMonth(),
                now()->startOfMonth(),
                20,
                now()->subMonth()->format('Y年n月'),
            ],
        };
    }

    /**
     * 前期間の順位と平均価格を取得
     * @return array{0: array<int,int>, 1: array<int,float>}
     */
    private function fetchPreviousPeriod(string $type, Carbon $currentStart, array $modelIds): array
    {
        [$prevStart, $prevEnd] = match ($type) {
            'daily' => [
                $currentStart->copy()->subDay(),
                $currentStart->copy(),
            ],
            'weekly' => [
                $currentStart->copy()->subWeek(),
                $currentStart->copy(),
            ],
            'monthly' => [
                $currentStart->copy()->subMonth(),
                $currentStart->copy(),
            ],
        };

        $prevData = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', $prevStart)
            ->where('updated_at', '<', $prevEnd)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->get();

        $prevRanks = [];
        foreach ($prevData->values() as $i => $row) {
            $prevRanks[$row->bike_model_id] = $i + 1;
        }

        // 前期間の平均価格（今期ランクイン車種のみ）
        $prevAvgPrices = [];
        if (!empty($modelIds)) {
            $prevAvgPrices = Listing::where('is_sold_out', true)
                ->where('updated_at', '>=', $prevStart)
                ->where('updated_at', '<', $prevEnd)
                ->whereIn('bike_model_id', $modelIds)
                ->whereNotNull('total_price')
                ->select('bike_model_id', DB::raw('ROUND(AVG(total_price) / 10000, 1) as avg_price'))
                ->groupBy('bike_model_id')
                ->pluck('avg_price', 'bike_model_id')
                ->all();
        }

        return [$prevRanks, $prevAvgPrices];
    }

    /**
     * 順位変動の自動コメントを生成
     */
    private function generateComment(
        int $rank,
        ?int $prevRank,
        int $soldCount,
        ?float $currentPrice,
        ?float $prevPrice,
    ): ?string {
        $isNew = $prevRank === null;
        $rankChange = $prevRank !== null ? $prevRank - $rank : 0; // 正=ランクアップ

        $priceUp = 0.0;
        $priceDown = 0.0;
        if ($currentPrice && $prevPrice) {
            $diff = $currentPrice - $prevPrice;
            if ($diff > 0) $priceUp = round($diff, 1);
            if ($diff < 0) $priceDown = round(abs($diff), 1);
        }

        return match (true) {
            $rank === 1 && $prevRank === 1 => '連続1位！圧倒的な人気を維持',
            $rank === 1 && $prevRank !== 1 => '今期ついに1位に！',
            $rankChange >= 5              => "{$rankChange}ランクの大幅ジャンプ！注目度急上昇",
            $rankChange >= 3              => '人気上昇中！',
            $rankChange <= -5             => '大幅ダウン…在庫減少の影響か',
            $rankChange <= -3             => 'やや人気下降気味',
            $isNew                        => '初のランクイン！要注目',
            $priceDown > 3                => "平均価格が{$priceDown}万円ダウン、買い時かも",
            $priceUp > 3                  => "平均価格が{$priceUp}万円アップ、高値傾向",
            $soldCount >= 200             => '安定した販売台数をキープ',
            default                       => null,
        };
    }

    private function buildContent(
        $rankings,
        $models,
        $avgPrices,
        array $prevRanks,
        array $prevAvgPrices,
        string $periodLabel,
    ): string {
        $html = '<div class="ranking-news">';
        $html .= '<p class="text-sm text-gray-600 mb-4">MotoHubの販売データから集計した' . e($periodLabel) . 'のバイク売れ筋ランキングです。</p>';

        foreach ($rankings->values() as $i => $row) {
            $rank = $i + 1;
            $m = $models->get($row->bike_model_id);
            if (!$m) continue;

            $name = e($m->name);
            $mfr = e($m->manufacturer->name ?? '不明');
            $sold = number_format($row->sold_count);
            $currentPrice = isset($avgPrices[$row->bike_model_id]) ? (float) $avgPrices[$row->bike_model_id] : null;
            $price = $currentPrice !== null ? $currentPrice . '万円' : '-';
            $imgUrl = e($this->resolveImageUrl($m) ?? '');
            $medal = match ($rank) {
                1 => '🥇',
                2 => '🥈',
                3 => '🥉',
                default => null,
            };

            // 順位変動（全タイプ共通）
            $prevRank = $prevRanks[$row->bike_model_id] ?? null;
            $change = '';
            if (!empty($prevRanks)) {
                if ($prevRank === null) {
                    $change = '<span class="text-orange-500 text-xs font-bold ml-1">NEW</span>';
                } elseif ($prevRank > $rank) {
                    $change = '<span class="text-green-600 text-sm font-bold ml-1">↑' . ($prevRank - $rank) . '</span>';
                } elseif ($prevRank < $rank) {
                    $change = '<span class="text-red-500 text-sm font-bold ml-1">↓' . ($rank - $prevRank) . '</span>';
                } else {
                    $change = '<span class="text-gray-400 text-xs ml-1">→</span>';
                }
            }

            // 自動コメント
            $prevPrice = isset($prevAvgPrices[$row->bike_model_id]) ? (float) $prevAvgPrices[$row->bike_model_id] : null;
            $comment = $this->generateComment($rank, $prevRank, $row->sold_count, $currentPrice, $prevPrice);
            $commentHtml = $comment ? '<div class="text-xs text-gray-500 mt-1">💬 ' . e($comment) . '</div>' : '';

            $imgTag = $imgUrl
                ? '<img src="' . $imgUrl . '" alt="' . $name . '" class="%imgclass%" loading="lazy" onerror="this.style.display=\'none\'">'
                : '<div class="%imgclass% bg-gray-200 flex items-center justify-center text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg></div>';

            if ($rank <= 3) {
                // TOP3: 大きめカード
                $img = str_replace('%imgclass%', 'w-24 h-16 object-cover rounded', $imgTag);
                $html .= '<div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg mb-3">';
                $html .= '<div class="text-2xl font-bold">' . $medal . '</div>';
                $html .= $img;
                $html .= '<div>';
                $html .= '<div class="font-bold">' . $name . $change . '</div>';
                $html .= '<div class="text-sm text-gray-500">' . $mfr . '</div>';
                $html .= '<div class="text-blue-600 font-bold">' . $sold . '台 / 平均' . e($price) . '</div>';
                $html .= $commentHtml;
                $html .= '</div></div>';
            } else {
                // 4位以降: コンパクトリスト
                $img = str_replace('%imgclass%', 'w-16 h-10 object-cover rounded', $imgTag);
                $html .= '<div class="flex items-center gap-3 py-2 border-b border-gray-100">';
                $html .= '<span class="w-8 text-center font-bold text-gray-500">' . $rank . $change . '</span>';
                $html .= $img;
                $html .= '<div class="flex-1 min-w-0">';
                $html .= '<span class="font-bold">' . $name . '</span>';
                $html .= '<span class="text-gray-500 text-sm ml-1">' . $mfr . '</span>';
                if ($commentHtml) {
                    $html .= $commentHtml;
                }
                $html .= '</div>';
                $html .= '<span class="text-blue-600 font-bold whitespace-nowrap">' . $sold . '台</span>';
                $html .= '</div>';
            }
        }

        $html .= '<p class="text-xs text-gray-400 mt-4">※ MotoHubに掲載された中古バイクの販売データに基づく集計です。</p>';
        $html .= '<a href="' . route('ranking.index') . '" class="inline-block mt-3 text-sm font-bold text-blue-600 hover:underline">詳しいランキングはこちら →</a>';
        $html .= '</div>';

        return $html;
    }

    private function resolveImageUrl(?BikeModel $model): ?string
    {
        if (!$model) return null;

        // 1. BikeModel.local_image_path
        if (is_array($model->local_image_path) && !empty($model->local_image_path)) {
            return asset('storage/' . ltrim($model->local_image_path[0], '/'));
        }

        // 2. BikeModel.image_url アクセサ
        $imageUrl = $model->image_url;
        if ($imageUrl) return $imageUrl;

        // 3. Manufacturer logo
        if ($model->manufacturer) {
            if ($model->manufacturer->local_logo_path) {
                return asset('storage/' . ltrim($model->manufacturer->local_logo_path, '/'));
            }
            if ($model->manufacturer->logo_url) {
                return $model->manufacturer->logo_url;
            }
        }

        return null;
    }
}
