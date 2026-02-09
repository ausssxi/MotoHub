<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Review;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetReviews extends Command
{
    protected $signature = 'bikes:tweet-reviews';
    protected $description = '新着レビューをX(Twitter)に投稿します';

    public function handle(): void
    {
        $this->info('新着レビューの探索を開始します...');

        // 未ツイートの承認済みレビューを1件取得（古い順＝投稿された順に消化）
        $review = Review::with(['bikeModel.manufacturer'])
            ->where('is_approved', true)
            ->whereNull('tweeted_at')
            ->orderBy('created_at', 'asc') 
            ->first();

        if (!$review) {
            $this->info('未ツイートのレビューはありませんでした。');
            return;
        }

        try {
            $connection = new TwitterOAuth(
                config('services.twitter.consumer_key'),
                config('services.twitter.consumer_secret'),
                config('services.twitter.access_token'),
                config('services.twitter.access_token_secret')
            );
            $connection->setApiVersion('2');
        } catch (\Exception $e) {
            $this->error('Twitter接続エラー: 設定を確認してください。');
            return;
        }

        // --- ツイート本文の作成 ---
        $bikeName = $review->bikeModel->name ?? '車種不明';
        $makerName = $review->bikeModel->manufacturer->name ?? '';
        
        // 星評価の文字列表現 (★★★★★)
        $stars = str_repeat('★', (int)$review->rating) . str_repeat('☆', 5 - (int)$review->rating);

        $text = "🗣️ 新着レビューが届きました！\n\n";
        $text .= "🏍️ {$bikeName} ({$makerName})\n";
        $text .= "⭐️ 評価: {$stars}\n\n";
        $text .= "「{$review->title}」\n";
        
        // 本文を少しだけ抜粋
        $bodySnippet = mb_substr($review->body, 0, 40);
        if (mb_strlen($review->body) > 40) $bodySnippet .= '...';
        $text .= "{$bodySnippet}\n\n";

        $text .= "👇 全文を読む\n";
        $text .= route('bikes.model_detail', $review->bike_model_id) . "#reviews\n\n";
        
        // ★修正: ハッシュタグの生成（車種名を追加）
        // スペース、カッコ、スラッシュなどを除去してハッシュタグとして有効な形式にする
        $cleanMakerName = preg_replace('/[\s　\(\)（）\/]+/u', '', $makerName);
        $cleanBikeName = preg_replace('/[\s　\(\)（）\/]+/u', '', $bikeName);

        $hashtags = "#MotoHub #バイクレビュー";
        
        if ($cleanMakerName) {
            $hashtags .= " #{$cleanMakerName}";
        }
        if ($cleanBikeName && $cleanBikeName !== '車種不明') {
            $hashtags .= " #{$cleanBikeName}";
        }

        $text .= $hashtags;

        // --- 画像生成 ---
        // レビュー専用の画像を生成します（車種名 + 星評価 + タイトル）
        $mediaIds = [];
        $generatedPath = $this->generateReviewImage($bikeName, $stars, $review->title);
        
        if ($generatedPath) {
            try {
                $connection->setApiVersion('1.1');
                $media = $connection->upload('media/upload', ['media' => $generatedPath]);
                if (isset($media->media_id_string)) {
                    $mediaIds[] = $media->media_id_string;
                }
            } catch (\Exception $e) {
                $this->error("Image upload failed: " . $e->getMessage());
            } finally {
                $connection->setApiVersion('2');
                // 生成した一時画像は削除
                if (file_exists($generatedPath)) unlink($generatedPath);
            }
        }

        // --- 投稿実行 ---
        $payload = ['text' => $text];
        if (!empty($mediaIds)) $payload['media'] = ['media_ids' => $mediaIds];

        $result = $connection->post('tweets', $payload);

        if ($connection->getLastHttpCode() == 201) {
            $this->info("Tweeted Review ID: {$review->id}");
            $review->update(['tweeted_at' => now()]);
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (Review)", (array)$result);
        }
    }

    /**
     * レビュー用画像を生成する
     */
    private function generateReviewImage(string $bikeName, string $stars, string $title): ?string
    {
        $fontPath = public_path('fonts/font.ttf');
        $templatePath = public_path('images/twitter_template.jpg'); // 既存のテンプレートを流用
        $isPng = false;

        if (!file_exists($templatePath)) {
            $templatePath = public_path('images/twitter_template.png');
            $isPng = true;
        }

        if (!file_exists($templatePath) || !file_exists($fontPath) || !extension_loaded('gd')) {
            return null;
        }

        try {
            if ($isPng) {
                $srcImage = imagecreatefrompng($templatePath);
                $width = imagesx($srcImage);
                $height = imagesy($srcImage);
                $image = imagecreatetruecolor($width, $height);
                $whiteBg = imagecolorallocate($image, 255, 255, 255);
                imagefilledrectangle($image, 0, 0, $width, $height, $whiteBg);
                imagecopy($image, $srcImage, 0, 0, 0, 0, $width, $height);
                imagedestroy($srcImage);
            } else {
                $image = imagecreatefromjpeg($templatePath);
            }

            if (!$image) return null;

            $black = imagecolorallocate($image, 0, 0, 0);
            $yellow = imagecolorallocate($image, 230, 180, 0); // 星用の黄色（暗め）
            $blue = imagecolorallocate($image, 0, 100, 200);

            // 車種名 (長い場合は省略)
            if (mb_strlen($bikeName) > 16) {
                $bikeName = mb_substr($bikeName, 0, 15) . '...';
            }
            
            // タイトル (長い場合は省略)
            if (mb_strlen($title) > 12) {
                $title = mb_substr($title, 0, 11) . '...';
            }

            // 描画 (X, Y座標はテンプレートに合わせて調整してください)
            // 1. 車種名
            imagettftext($image, 28, 0, 50, 180, $black, $fontPath, $bikeName); 
            
            // 2. 星評価
            imagettftext($image, 40, 0, 50, 280, $yellow, $fontPath, $stars);

            // 3. レビュータイトル
            imagettftext($image, 32, 0, 50, 380, $blue, $fontPath, "「{$title}」");

            $tempPath = storage_path('app/public/temp_review_' . uniqid() . '.jpg');
            imagejpeg($image, $tempPath, 90); 
            imagedestroy($image);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error("Review Image Gen Error: " . $e->getMessage());
            return null;
        }
    }
}