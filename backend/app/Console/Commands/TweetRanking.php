<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeNews;
use App\Models\Listing;
use App\Models\BikeModel;
use Abraham\TwitterOAuth\TwitterOAuth;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TweetRanking extends Command
{
    protected $signature = 'twitter:post-ranking
                            {--type=daily : daily|weekly|monthly}
                            {--dry-run : ツイートせずにテキストだけ表示}';

    protected $description = 'ランキングニュースをX(Twitter)に自動投稿';

    public function handle(): void
    {
        $type = $this->option('type');

        if (!in_array($type, ['daily', 'weekly', 'monthly'], true)) {
            $this->error("無効なタイプ: {$type}");
            return;
        }

        $this->info("ランキング投稿開始: {$type}");

        // 期間パラメータ
        [$startDate, $endDate, $limit] = match ($type) {
            'daily' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::today()->startOfDay(),
                10,
            ],
            'weekly' => [
                now()->subWeek()->startOfWeek(),
                now()->startOfWeek(),
                10,
            ],
            'monthly' => [
                now()->subMonth()->startOfMonth(),
                now()->startOfMonth(),
                20,
            ],
        };

        // TOP車種を集計
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', $startDate)
            ->where('updated_at', '<', $endDate)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn('データ不足のためスキップ');
            return;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        // 前期間の順位（変動表示用）
        $prevRanks = $this->fetchPrevRanks($type, $startDate);

        // 該当ニュース記事のURLを取得
        $newsUrl = $this->findNewsUrl($type);

        // 投稿文を組み立て
        $text = $this->buildText($type, $rankings, $models, $prevRanks, $newsUrl, $startDate);

        if ($this->option('dry-run')) {
            $this->info("--- Dry Run ---");
            $this->line($text);
            $this->info('文字数: ' . mb_strlen($text));
            return;
        }

        $this->postTweet($text);
    }

    private function buildText(
        string $type,
        $rankings,
        $models,
        array $prevRanks,
        ?string $newsUrl,
        Carbon $startDate,
    ): string {
        $top = $rankings->values();
        $name = fn (int $i) => $models->get($top[$i]->bike_model_id)?->name ?? '不明';
        $count = fn (int $i) => $top[$i]->sold_count;

        // 順位変動テキスト
        $changeText = function (int $i) use ($top, $prevRanks): string {
            $modelId = $top[$i]->bike_model_id;
            $rank = $i + 1;
            $prev = $prevRanks[$modelId] ?? null;
            if ($prev === null) return ' 🆕';
            if ($prev > $rank) return ' ↑' . ($prev - $rank);
            if ($prev < $rank) return ' ↓' . ($rank - $prev);
            return ' →';
        };

        $link = $newsUrl ?? 'https://www.motohub.jp/ranking';

        return match ($type) {
            'daily' => $this->buildDaily($startDate, $name, $count, $link),
            'weekly' => $this->buildWeekly($name, $count, $changeText, $rankings, $link),
            'monthly' => $this->buildMonthly($startDate, $name, $count, $top, $models, $link),
        };
    }

    private function buildDaily(Carbon $startDate, \Closure $name, \Closure $count, string $link): string
    {
        $date = Carbon::yesterday()->format('n月j日');

        $body = "🏍️【MotoHub調べ】{$date} バイク売れ筋デイリーランキング\n\n"
            . "🥇 {$name(0)}（{$count(0)}台）\n"
            . "🥈 {$name(1)}（{$count(1)}台）\n"
            . "🥉 {$name(2)}（{$count(2)}台）\n\n"
            . "詳しくはこちら👇\n"
            . "{$link}\n\n";

        $tags = $this->buildTags([$name(0), $name(1), $name(2)], '#売れ筋ランキング');

        return $body . implode(' ', $tags);
    }

    private function buildWeekly(\Closure $name, \Closure $count, \Closure $changeText, $rankings, string $link): string
    {
        $totalSold = $rankings->sum('sold_count');

        $body = "���【MotoHub調べ】先週のバイク売れ筋ランキング\n\n"
            . "🥇 {$name(0)}（{$count(0)}台）{$changeText(0)}\n"
            . "🥈 {$name(1)}（{$count(1)}台）{$changeText(1)}\n"
            . "🥉 {$name(2)}（{$count(2)}台）{$changeText(2)}\n\n"
            . number_format($totalSold) . "台の販売データから集計📈\n"
            . "{$link}\n\n";

        $tags = $this->buildTags([$name(0), $name(1), $name(2)], '#売れ筋ランキング');

        return $body . implode(' ', $tags);
    }

    private function buildMonthly(Carbon $startDate, \Closure $name, \Closure $count, $top, $models, string $link): string
    {
        $month = $startDate->format('n');
        $name4 = $models->get($top[3]->bike_model_id)?->name ?? '不明';
        $name5 = $models->get($top[4]->bike_model_id)?->name ?? '不明';

        $body = "🏆【MotoHub調べ】{$month}月のバイク売れ筋ランキング\n\n"
            . "🥇 {$name(0)}（{$count(0)}台）\n"
            . "🥈 {$name(1)}（{$count(1)}台）\n"
            . "🥉 {$name(2)}（{$count(2)}台）\n"
            . "4位 {$name4} / 5位 {$name5}\n\n"
            . "メディア・ブロガーの方へ：\n"
            . "このデータは自由にお使いいただけます📊\n"
            . "詳細→ {$link}\n\n";

        $tags = $this->buildTags([$name(0), $name(1), $name(2)], '#バイクランキング');

        return $body . implode(' ', $tags);
    }

    private function buildTags(array $modelNames, string $topicTag): array
    {
        $tags = [
            '#バイク乗りと繋がりたい', '#バイク好きと繋がりたい', '#バイクのある生活',
            '#中古バイク', '#MotoHub', '#ツーリング',
            $topicTag,
        ];

        foreach ($modelNames as $name) {
            if ($name && $name !== '不明') {
                $clean = preg_replace('/[\s　\(\)（）\/]+/u', '', $name);
                if ($clean) $tags[] = "#{$clean}";
            }
        }

        return array_slice(array_unique($tags), 0, 13);
    }

    private function fetchPrevRanks(string $type, Carbon $currentStart): array
    {
        [$prevStart, $prevEnd] = match ($type) {
            'daily'   => [$currentStart->copy()->subDay(), $currentStart->copy()],
            'weekly'  => [$currentStart->copy()->subWeek(), $currentStart->copy()],
            'monthly' => [$currentStart->copy()->subMonth(), $currentStart->copy()],
        };

        $prevData = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', $prevStart)
            ->where('updated_at', '<', $prevEnd)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->get();

        $ranks = [];
        foreach ($prevData->values() as $i => $row) {
            $ranks[$row->bike_model_id] = $i + 1;
        }

        return $ranks;
    }

    private function findNewsUrl(string $type): ?string
    {
        $typeLabel = match ($type) {
            'daily'   => 'デイリー',
            'weekly'  => 'ウィークリー',
            'monthly' => 'マンスリー',
        };

        $news = BikeNews::where('source', 'MotoHub')
            ->where('title', 'like', "%{$typeLabel}%")
            ->orderByDesc('published_at')
            ->first();

        if ($news) {
            return route('news.show', $news->id);
        }

        return null;
    }

    private function postTweet(string $text): void
    {
        try {
            $connection = new TwitterOAuth(
                config('services.twitter.consumer_key'),
                config('services.twitter.consumer_secret'),
                config('services.twitter.access_token'),
                config('services.twitter.access_token_secret')
            );
            $connection->setApiVersion('2');
        } catch (\Exception $e) {
            $this->error('Twitter接続エラー: ' . $e->getMessage());
            Log::error('Twitter connection failed (Ranking)', ['error' => $e->getMessage()]);
            return;
        }

        $result = $connection->post('tweets', ['text' => $text]);

        if ($connection->getLastHttpCode() == 201) {
            $this->info('ランキングをツイートしました');
        } else {
            $this->error('Tweet failed code: ' . $connection->getLastHttpCode());
            Log::error('Twitter API Error (Ranking)', (array) $result);
        }
    }
}
