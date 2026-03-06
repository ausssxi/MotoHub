<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PriceHistory;
use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Support\Facades\Log;

class TweetPriceDrop extends Command
{
    protected $signature = 'bikes:tweet-price-drop {--dry-run : ツイートせずにテキストだけ表示}';
    protected $description = '値下げ速報をX(Twitter)に投稿します';

    public function handle(): void
    {
        $this->info('値下げ速報の探索を開始します...');

        $priceHistory = PriceHistory::with(['listing.bikeModel.manufacturer'])
            ->where('is_notified', false)
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('listing', function ($q) {
                $q->where('is_sold_out', false);
            })
            ->orderByRaw('(old_price - new_price) DESC')
            ->first();

        if (!$priceHistory) {
            $this->info('直近24時間の値下げ情報はありませんでした。');
            return;
        }

        $listing = $priceHistory->listing;
        $bikeModel = $listing->bikeModel;
        $makerName = $bikeModel?->manufacturer?->name ?? '';
        $bikeName = $bikeModel?->name ?? '車種不明';
        $seoUrl = $bikeModel?->seo_url ?? '';

        $oldPriceMan = number_format($priceHistory->old_price / 10000, 1);
        $newPriceMan = number_format($priceHistory->new_price / 10000, 1);
        $diffMan = number_format(($priceHistory->old_price - $priceHistory->new_price) / 10000, 1);

        // ハッシュタグ生成
        $cleanMakerName = preg_replace('/[\s　\(\)（）\/]+/u', '', $makerName);
        $cleanBikeName = preg_replace('/[\s　\(\)（）\/]+/u', '', $bikeName);

        $hashtags = "#MotoHub #バイク値下げ #中古バイク";
        if ($cleanMakerName) $hashtags .= " #{$cleanMakerName}";
        if ($cleanBikeName && $cleanBikeName !== '車種不明') $hashtags .= " #{$cleanBikeName}";

        $makerTags = $this->getMakerHashtags($cleanMakerName);
        if ($makerTags) $hashtags .= " " . $makerTags;

        $text = "📉 値下げ速報！\n\n";
        $text .= "🏍️ {$bikeName} ({$makerName})\n";
        $text .= "💰 {$oldPriceMan}万円 → {$newPriceMan}万円（-{$diffMan}万円）\n\n";
        $text .= "今が買い時かも！👇\n";
        $text .= "https://www.motohub.jp{$seoUrl}\n\n";
        $text .= $hashtags;

        if ($this->option('dry-run')) {
            $this->info("--- Dry Run ---");
            $this->line($text);
            return;
        }

        // Twitter接続
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

        // 画像生成
        $mediaIds = [];
        $generatedPath = $this->generatePriceDropImage(
            $bikeName,
            $oldPriceMan . '万円',
            $newPriceMan . '万円',
            $diffMan . '万円'
        );

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
                if (file_exists($generatedPath)) unlink($generatedPath);
            }
        }

        // ツイート投稿
        $payload = ['text' => $text];
        if (!empty($mediaIds)) $payload['media'] = ['media_ids' => $mediaIds];

        $result = $connection->post('tweets', $payload);

        if ($connection->getLastHttpCode() == 201) {
            $this->info("値下げ速報をツイートしました: {$bikeName}");
            $priceHistory->update(['is_notified' => true]);
        } else {
            $this->error("Tweet failed code: " . $connection->getLastHttpCode());
            Log::error("Twitter API Error (PriceDrop)", (array)$result);
        }
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

    private function generatePriceDropImage(string $bikeName, string $oldPrice, string $newPrice, string $diff): ?string
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

            $black = imagecolorallocate($image, 0, 0, 0);
            $red = imagecolorallocate($image, 220, 38, 38);
            $green = imagecolorallocate($image, 22, 163, 74);
            $gray = imagecolorallocate($image, 120, 120, 120);

            if (mb_strlen($bikeName) > 18) {
                $bikeName = mb_substr($bikeName, 0, 17) . '...';
            }

            // 車種名
            imagettftext($image, 28, 0, 50, 180, $black, $fontPath, $bikeName);

            // 旧価格（取り消し線風・グレー）
            imagettftext($image, 30, 0, 50, 270, $gray, $fontPath, $oldPrice);

            // 赤い矢印
            imagettftext($image, 50, 0, 50, 370, $red, $fontPath, '↓');

            // 新価格（赤・大きめ）
            imagettftext($image, 50, 0, 120, 370, $red, $fontPath, $newPrice);

            // 差額（緑）
            imagettftext($image, 40, 0, 50, 470, $green, $fontPath, "-{$diff} お得！");

            $tempPath = storage_path('app/public/temp_pricedrop_' . uniqid() . '.jpg');
            imagejpeg($image, $tempPath, 90);
            imagedestroy($image);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error("PriceDrop Image Gen Error: " . $e->getMessage());
            return null;
        }
    }
}
