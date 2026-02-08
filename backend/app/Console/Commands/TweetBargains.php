<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetBargains extends Command
{
    protected $signature = 'bikes:tweet-bargains';
    protected $description = 'お買い得車両を探してX(Twitter)に投稿します';

    public function handle(): void
    {
        $this->info('お買い得車両の探索を開始します...');

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

        // N+1問題を避けるため with で関連データをロード
        $listings = Listing::with(['bikeModel.manufacturer'])
            ->whereNull('tweeted_at')
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $tweetCount = 0;
        $maxTweets = 3; 

        foreach ($listings as $listing) {
            if ($tweetCount >= $maxTweets) {
                $this->info("投稿上限({$maxTweets}件)に達したため終了します。");
                break;
            }

            $averagePrice = Listing::where('bike_model_id', $listing->bike_model_id)
                ->where('is_sold_out', false)
                ->where('id', '!=', $listing->id)
                ->avg('total_price');

            if (!$averagePrice || $averagePrice == 0) {
                $listing->update(['tweeted_at' => now()]);
                continue;
            }

            $discountRate = 0.8; 
            
            if ($listing->total_price < ($averagePrice * $discountRate)) {
                
                $diff = floor($averagePrice - $listing->total_price);
                $priceInMan = number_format($listing->total_price / 10000, 1);
                $diffInMan = number_format($diff / 10000, 1);
                
                // 車両名とメーカー名を取得
                $bikeName = $listing->title ?? $listing->bikeModel?->name ?? '車種名不明';
                $makerName = $listing->bikeModel?->manufacturer?->name ?? '';
                
                // ★追加: ハッシュタグの生成
                // スペース、カッコ、スラッシュなどを除去してハッシュタグとして有効な形式にする
                $cleanBikeName = preg_replace('/[\s　\(\)（）\/]+/u', '', $bikeName);
                $cleanMakerName = preg_replace('/[\s　\(\)（）\/]+/u', '', $makerName);

                $hashtags = "#中古バイク #MotoHub"; // 固定タグ
                if ($cleanMakerName) {
                    $hashtags .= " #{$cleanMakerName}";
                }
                if ($cleanBikeName && $cleanBikeName !== '車種名不明') {
                    $hashtags .= " #{$cleanBikeName}";
                }

                $text = "🚨 激安速報！\n\n";
                $text .= "🏍 {$bikeName}\n"; 
                $text .= "💰 価格: {$priceInMan}万円\n";
                $text .= "📉 相場平均より 約{$diffInMan}万円 お得です！\n\n";
                $text .= route('bikes.show', $listing->id) . "\n\n"; 
                $text .= $hashtags; // ★ハッシュタグを追加

                // --- 画像準備 ---
                $mediaIds = [];
                $uploadImagePath = null;
                $isGenerated = false;

                $generatedPath = $this->generateCardImage($bikeName, $priceInMan . '万円');
                
                if ($generatedPath) {
                    $uploadImagePath = $generatedPath;
                    $isGenerated = true;
                    $this->info("Generated custom image for: {$bikeName}");
                } else {
                    $fixedPath = public_path('images/twitter_card.jpg');
                    if (!file_exists($fixedPath)) {
                        $fixedPath = public_path('images/twitter_card.png');
                    }
                    if (file_exists($fixedPath)) {
                        $uploadImagePath = $fixedPath;
                    }
                }

                // --- 画像アップロード ---
                if ($uploadImagePath) {
                    try {
                        $connection->setApiVersion('1.1');
                        $media = $connection->upload('media/upload', ['media' => $uploadImagePath]);
                        
                        if (isset($media->media_id_string)) {
                            $mediaIds[] = $media->media_id_string;
                        }
                    } catch (\Exception $e) {
                        $this->error("Image upload failed: " . $e->getMessage());
                    } finally {
                        $connection->setApiVersion('2');
                        if ($isGenerated && file_exists($uploadImagePath)) {
                            unlink($uploadImagePath);
                        }
                    }
                }

                // --- ツイート投稿 ---
                $payload = ['text' => $text];
                if (!empty($mediaIds)) {
                    $payload['media'] = ['media_ids' => $mediaIds];
                }

                $result = $connection->post('tweets', $payload);

                if ($connection->getLastHttpCode() == 201) {
                    $this->info("Tweeted: {$bikeName} (ID: {$listing->id})");
                    $listing->update(['tweeted_at' => now()]);
                    $tweetCount++;
                } else {
                    $this->error("Tweet failed code: " . $connection->getLastHttpCode());
                    Log::error("Twitter API Error", (array)$result);
                    break; 
                }
            } else {
                $listing->update(['tweeted_at' => now()]);
            }
        }

        $this->info("完了: {$tweetCount}件ツイートしました。");
    }

    /**
     * 車種名と価格を入れた画像を生成する
     */
    private function generateCardImage(string $bikeName, string $priceText): ?string
    {
        $fontPath = public_path('fonts/font.ttf');

        $templatePath = public_path('images/twitter_template.jpg');
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

            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0); 
            $red   = imagecolorallocate($image, 255, 50, 50);

            if (mb_strlen($bikeName) > 18) {
                $bikeName = mb_substr($bikeName, 0, 17) . '...';
            }

            imagettftext($image, 32, 0, 53, 203, $black, $fontPath, $bikeName);
            imagettftext($image, 32, 0, 50, 200, $black, $fontPath, $bikeName); 

            imagettftext($image, 50, 0, 53, 353, $black, $fontPath, $priceText);
            imagettftext($image, 50, 0, 50, 350, $red,   $fontPath, $priceText);

            $tempPath = storage_path('app/public/temp_tweet_' . uniqid() . '.jpg');
            imagejpeg($image, $tempPath, 90); 
            imagedestroy($image);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error("Image Generation Error: " . $e->getMessage());
            return null;
        }
    }
}