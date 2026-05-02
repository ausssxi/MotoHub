<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Models\BikeNews;
use App\Services\Bike\TrendService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class TweetLatestReport extends Command
{
    protected $signature = 'news:tweet-latest-report
                            {--dry-run : ツイートせずにテキストだけ表示}';

    protected $description = '最新の相場レポートニュースをX(Twitter)に自動投稿';

    public function handle(TrendService $trendService): int
    {
        $news = BikeNews::where('source', 'MotoHub')
            ->where(fn ($q) => $q
                ->where('title', 'like', '%相場速報%')
                ->orWhere('title', 'like', '%市場レポート%')
                ->orWhere('title', 'like', '%週間相場速報%')
                ->orWhere('title', 'like', '%新型%')
                ->orWhere('title', 'like', '%モデルチェンジ%')
            )
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();

        if (!$news) {
            $this->warn('投稿対象の相場レポートが見つかりません。');
            return self::SUCCESS;
        }

        $this->info("対象記事: {$news->title}");

        $trends = $trendService->getRanking(30);
        $topDrop = $trends['drop'][0] ?? null;
        $topRise = $trends['rise'][0] ?? null;

        $newsUrl = route('news.show', $news->id);
        $text = $this->buildText($news->title, $topDrop, $topRise, $newsUrl);

        if ($this->option('dry-run')) {
            $this->info('--- Dry Run ---');
            $this->line($text);
            $this->info('文字数: ' . mb_strlen($text));
            return self::SUCCESS;
        }

        $this->postTweet($text);

        return self::SUCCESS;
    }

    private function buildText(string $title, ?array $topDrop, ?array $topRise, string $newsUrl): string
    {
        $text = "📊 {$title}\n\n";

        if ($topDrop) {
            $text .= "📉 値下がり注目: {$topDrop['model_name']}が{$topDrop['diff']}万円\n";
        }
        if ($topRise) {
            $text .= "📈 高騰注目: {$topRise['model_name']}が+{$topRise['diff']}万円\n";
        }

        $text .= "\n詳細はこちら👇\n{$newsUrl}\n\n";
        $text .= "#中古バイク #バイク相場 #MotoHub\n";
        $text .= "#バイク乗りと繋がりたい #バイクのある生活\n";
        $text .= '#バイク好きと繋がりたい';

        return $text;
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
            Log::error('Twitter connection failed (MarketReport)', ['error' => $e->getMessage()]);
            return;
        }

        $result = $connection->post('tweets', ['text' => $text]);

        if ($connection->getLastHttpCode() == 201) {
            $this->info('相場レポートをツイートしました');
        } else {
            $this->error('Tweet failed code: ' . $connection->getLastHttpCode());
            Log::error('Twitter API Error (MarketReport)', (array) $result);
        }
    }
}
