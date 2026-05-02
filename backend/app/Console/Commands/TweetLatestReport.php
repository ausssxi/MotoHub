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
        $news = BikeNews::with('bikeModel.manufacturer')
            ->where('source', 'MotoHub')
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
        $text = $this->buildText($news, $topDrop, $topRise, $newsUrl);

        if ($this->option('dry-run')) {
            $this->info('--- Dry Run ---');
            $this->line($text);
            $this->info('文字数: ' . mb_strlen($text));
            return self::SUCCESS;
        }

        $this->postTweet($text);

        return self::SUCCESS;
    }

    private function buildText(BikeNews $news, ?array $topDrop, ?array $topRise, string $newsUrl): string
    {
        $text = "📊 {$news->title}\n\n";

        if ($topDrop) {
            $text .= "📉 値下がり注目: {$topDrop['model_name']}が{$topDrop['diff']}万円\n";
        }
        if ($topRise) {
            $text .= "📈 高騰注目: {$topRise['model_name']}が+{$topRise['diff']}万円\n";
        }

        $text .= "\n詳細はこちら👇\n{$newsUrl}\n\n";
        $text .= implode(' ', $this->buildTags($news, $topDrop, $topRise));

        return $text;
    }

    private function buildTags(BikeNews $news, ?array $topDrop, ?array $topRise): array
    {
        // 共通コミュニティタグ
        $tags = [
            '#バイク乗りと繋がりたい', '#バイク好きと繋がりたい', '#バイクのある生活',
            '#中古バイク', '#MotoHub', '#ツーリング',
        ];

        // 記事内容に応じた専用タグ
        $title = $news->title;
        if (str_contains($title, '新型') || str_contains($title, 'モデルチェンジ')) {
            $tags[] = '#新型バイク';
        }
        if (str_contains($title, '相場') || str_contains($title, 'レポート')) {
            $tags[] = '#バイク相場';
        }

        // メーカータグ（記事に紐づくメーカー）
        $makerSlug = $news->bikeModel?->manufacturer?->slug;
        if ($makerSlug) {
            $tags = array_merge($tags, $this->makerTags($makerSlug));
        }

        // 車種名タグ（記事に紐づく車種）
        $bikeSlug = $news->bikeModel?->slug;
        if ($bikeSlug) {
            $tags[] = '#' . strtolower($bikeSlug);
        }

        // トレンド車種名タグ
        if ($topDrop) {
            $clean = preg_replace('/[\s　\(\)（）\/]+/u', '', $topDrop['model_name']);
            if ($clean) $tags[] = "#{$clean}";
        }
        if ($topRise) {
            $clean = preg_replace('/[\s　\(\)（）\/]+/u', '', $topRise['model_name']);
            if ($clean) $tags[] = "#{$clean}";
        }

        return array_slice(array_unique($tags), 0, 13);
    }

    private function makerTags(string $slug): array
    {
        return match ($slug) {
            'yamaha'   => ['#YAMAHAが美しい', '#yamaha'],
            'honda'    => ['#Honda党', '#honda'],
            'kawasaki' => ['#漢は黙ってカワサキ', '#kawasaki'],
            'suzuki'   => ['#鈴菌', '#suzuki'],
            default    => ["#{$slug}"],
        };
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
