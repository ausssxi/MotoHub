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

        // 検索条件
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

            $discountRate = 0.8; // 20%以上安いものを対象
            
            if ($listing->total_price < ($averagePrice * $discountRate)) {
                
                $diff = floor($averagePrice - $listing->total_price);
                $priceInMan = number_format($listing->total_price / 10000, 1);
                $diffInMan = number_format($diff / 10000, 1);
                
                // 割引率を計算 (画像生成に使用)
                $percentOff = (int)round((($averagePrice - $listing->total_price) / $averagePrice) * 100);

                // 車両名とメーカー名
                $displayName = $listing->title ?? $listing->bikeModel?->name ?? '車種名不明';
                $modelNameTag = $listing->bikeModel?->name ?? '';
                $makerName = $listing->bikeModel?->manufacturer?->name ?? '';
                
                // --- 文言の作成 ---
                $catchCopies = [
                    "🔥 激アツ車両発見！急げ！",
                    "📉 相場崩壊！？この価格は二度見するレベル",
                    "🏍️ 週末のツーリングに間に合うかも？",
                    "👀 探していた人、チャンスです！",
                    "💎 掘り出し物センサーが反応しました！"
                ];
                $catch = $catchCopies[array_rand($catchCopies)];

                // ハッシュタグ生成
                $cleanMakerName = preg_replace('/[\s　\(\)（）\/]+/u', '', $makerName);
                $cleanModelName = preg_replace('/[\s　\(\)（）\/]+/u', '', $modelNameTag);

                $hashtags = "#バイク乗りと繋がりたい #バイク売ります #中古バイク #MotoHub"; 
                if ($cleanMakerName) $hashtags .= " #{$cleanMakerName}";
                if ($cleanModelName) $hashtags .= " #{$cleanModelName}";
                
                // メーカータグ追加
                $makerTags = $this->getMakerHashtags($cleanMakerName);
                if ($makerTags) $hashtags .= " " . $makerTags;

                $text = "{$catch}\n\n";
                $text .= "🏍 {$displayName}\n"; 
                $text .= "💰 価格: {$priceInMan}万円\n";
                $text .= "（相場平均より {$percentOff}% OFF✨）\n\n";
                $text .= route('bikes.show', $listing->id) . "\n\n"; 
                $text .= $hashtags;

                // --- 画像準備 ---
                $mediaIds = [];
                $uploadImagePath = null;
                $isGenerated = false;

                // 割引率を渡して、インパクトのある画像を生成する
                $generatedPath = $this->generateCardImage($displayName, $priceInMan . '万円', $percentOff);
                
                if ($generatedPath) {
                    $uploadImagePath = $generatedPath;
                    $isGenerated = true;
                    $this->info("Generated custom image for: {$displayName} ({$percentOff}% OFF)");
                } else {
                    $fixedPath = public_path('images/twitter_card.jpg');
                    if (!file_exists($fixedPath)) $fixedPath = public_path('images/twitter_card.png');
                    if (file_exists($fixedPath)) $uploadImagePath = $fixedPath;
                }

                // --- 画像アップロード ---
                if ($uploadImagePath) {
                    try {
                        $connection->setApiVersion('1.1');
                        $media = $connection->upload('media/upload', ['media' => $uploadImagePath]);
                        if (isset($media->media_id_string)) $mediaIds[] = $media->media_id_string;
                    } catch (\Exception $e) {
                        $this->error("Image upload failed: " . $e->getMessage());
                    } finally {
                        $connection->setApiVersion('2');
                        if ($isGenerated && file_exists($uploadImagePath)) unlink($uploadImagePath);
                    }
                }

                // --- ツイート投稿 ---
                $payload = ['text' => $text];
                if (!empty($mediaIds)) $payload['media'] = ['media_ids' => $mediaIds];

                $result = $connection->post('tweets', $payload);

                if ($connection->getLastHttpCode() == 201) {
                    $this->info("Tweeted: {$displayName} (ID: {$listing->id})");
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

    private function getMakerHashtags(string $makerName): string
    {
        if (stripos($makerName, 'ヤマハ') !== false || stripos($makerName, 'Yamaha') !== false) return '#YAMAHAが美しい';
        if (stripos($makerName, 'カワサキ') !== false || stripos($makerName, 'Kawasaki') !== false) return '#漢は黙ってカワサキ';
        if (stripos($makerName, 'スズキ') !== false || stripos($makerName, 'Suzuki') !== false) return '#鈴菌';
        if (stripos($makerName, 'ホンダ') !== false || stripos($makerName, 'Honda') !== false) return '#HONDA';
        if (stripos($makerName, 'ハーレー') !== false || stripos($makerName, 'Harley') !== false) return '#HarleyDavidson';
        if (stripos($makerName, 'ドゥカティ') !== false || stripos($makerName, 'Ducati') !== false) return '#Ducati';
        return '';
    }

    /**
     * 割引率を受け取り、インパクトのある画像を生成する
     */
    private function generateCardImage(string $bikeName, string $priceText, int $percentOff): ?string
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
            // 画像リソース作成
            if ($isPng) {
                $srcImage = imagecreatefrompng($templatePath);
                $width = imagesx($srcImage);
                $height = imagesy($srcImage);
                
                $image = imagecreatetruecolor($width, $height);
                
                // ★背景色の決定: 割引率が15%以上なら黄色、それ以外は白
                if ($percentOff >= 15) {
                    // 注目色（黄色）
                    $bgColor = imagecolorallocate($image, 255, 235, 59); // yellow-400
                } else {
                    $bgColor = imagecolorallocate($image, 255, 255, 255); // white
                }
                
                imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
                // テンプレート（ロゴなど）を重ねる
                imagecopy($image, $srcImage, 0, 0, 0, 0, $width, $height);
                imagedestroy($srcImage);
            } else {
                $image = imagecreatefromjpeg($templatePath);
            }

            if (!$image) return null;

            // 色の定義
            $black = imagecolorallocate($image, 0, 0, 0); 
            $red   = imagecolorallocate($image, 220, 38, 38); // red-600
            $white = imagecolorallocate($image, 255, 255, 255);

            if (mb_strlen($bikeName) > 18) {
                $bikeName = mb_substr($bikeName, 0, 17) . '...';
            }

            // --- 描画処理 ---
            
            // 1. 車種名 (黒)
            imagettftext($image, 32, 0, 50, 200, $black, $fontPath, $bikeName); 

            // 2. 価格 (赤)
            imagettftext($image, 50, 0, 50, 350, $red, $fontPath, $priceText);
            
            // 3. 割引率 (デカ文字！)
            if ($percentOff > 0) {
                $offText = "{$percentOff}% OFF!!";
                // 影付きで描画
                imagettftext($image, 70, 0, 55, 505, $white, $fontPath, $offText); // 影（白抜き用）
                imagettftext($image, 70, 0, 50, 500, $red,   $fontPath, $offText); // 本体
            }

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