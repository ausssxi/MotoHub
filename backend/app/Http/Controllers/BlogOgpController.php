<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\Typography\FontFactory;

class BlogOgpController extends Controller
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const CACHE_DIR = 'ogp';

    public function show(BlogPost $post)
    {
        $cachePath = self::CACHE_DIR . "/{$post->slug}.png";

        // キャッシュがあればそのまま返す
        if (Storage::disk('public')->exists($cachePath)) {
            return $this->imageResponse(Storage::disk('public')->path($cachePath));
        }

        // OGP画像を生成
        $image = $this->generateImage($post->title);

        // キャッシュディレクトリを確保して保存
        Storage::disk('public')->makeDirectory(self::CACHE_DIR);
        Storage::disk('public')->put($cachePath, $image->toPng()->toString());

        return $this->imageResponse(Storage::disk('public')->path($cachePath));
    }

    private function generateImage(string $title): \Intervention\Image\Image
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->create(self::WIDTH, self::HEIGHT);

        // 背景グラデーション（#1a1a2e → #16213e）
        $this->drawGradientBackground($image);

        // 装飾ライン
        $this->drawAccentLines($image);

        // タイトル描画
        $this->drawTitle($image, $title);

        // MotoHub ブランディング（右下）
        $this->drawBranding($image);

        return $image;
    }

    /**
     * 縦グラデーション背景を描画
     */
    private function drawGradientBackground(\Intervention\Image\Image $image): void
    {
        $r1 = 0x1a; $g1 = 0x1a; $b1 = 0x2e;
        $r2 = 0x16; $g2 = 0x21; $b2 = 0x3e;

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / self::HEIGHT;
            $r = (int) ($r1 + ($r2 - $r1) * $ratio);
            $g = (int) ($g1 + ($g2 - $g1) * $ratio);
            $b = (int) ($b1 + ($b2 - $b1) * $ratio);
            $hex = sprintf('#%02x%02x%02x', $r, $g, $b);

            $image->drawLine(function (LineFactory $line) use ($y, $hex) {
                $line->from(0, $y);
                $line->to(self::WIDTH, $y);
                $line->color($hex);
            });
        }
    }

    /**
     * アクセント装飾ライン
     */
    private function drawAccentLines(\Intervention\Image\Image $image): void
    {
        // 上部アクセントライン（オレンジ系）
        for ($i = 0; $i < 4; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, $i);
                $line->to(self::WIDTH, $i);
                $line->color('#e67e22');
            });
        }

        // 下部サブライン
        for ($i = 0; $i < 2; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, self::HEIGHT - 1 - $i);
                $line->to(self::WIDTH, self::HEIGHT - 1 - $i);
                $line->color('rgba(230, 126, 34, 0.5)');
            });
        }
    }

    /**
     * タイトルテキストを描画（自動改行・フォントサイズ調整）
     */
    private function drawTitle(\Intervention\Image\Image $image, string $title): void
    {
        $fontPath = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $hasFont = file_exists($fontPath);

        // タイトル長に応じてフォントサイズ調整（1200×630に最適化）
        $titleLen = mb_strlen($title);
        if ($titleLen <= 15) {
            $fontSize = 72;
            $charsPerLine = 14;
        } elseif ($titleLen <= 25) {
            $fontSize = 64;
            $charsPerLine = 16;
        } elseif ($titleLen <= 40) {
            $fontSize = 56;
            $charsPerLine = 18;
        } else {
            $fontSize = 48;
            $charsPerLine = 20;
        }

        // 自動改行処理
        $lines = $this->wrapText($title, $charsPerLine, 4);
        $lineHeight = $hasFont ? (int) ($fontSize * 1.6) : 20;
        $totalHeight = count($lines) * $lineHeight;
        $startY = (int) ((self::HEIGHT - $totalHeight) / 2);

        foreach ($lines as $i => $line) {
            $y = $startY + ($i * $lineHeight);

            if ($hasFont) {
                $image->text($line, (int) (self::WIDTH / 2), $y, function (FontFactory $font) use ($fontPath, $fontSize) {
                    $font->filename($fontPath);
                    $font->size($fontSize);
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('top');
                });
            } else {
                // フォント未配置時: GD組み込みフォントで描画
                $image->text($line, (int) (self::WIDTH / 2), $y, function (FontFactory $font) {
                    $font->size(5); // GD built-in font size (1-5)
                    $font->color('#ffffff');
                    $font->align('center');
                    $font->valign('top');
                });
            }
        }
    }

    /**
     * テキストを指定文字数で改行し、最大行数で切り詰め
     */
    private function wrapText(string $text, int $charsPerLine, int $maxLines): array
    {
        $lines = [];
        $remaining = $text;

        while (mb_strlen($remaining) > 0 && count($lines) < $maxLines) {
            if (mb_strlen($remaining) <= $charsPerLine) {
                $lines[] = $remaining;
                break;
            }

            // 最終行なら「…」付きで切り詰め
            if (count($lines) === $maxLines - 1) {
                $lines[] = mb_substr($remaining, 0, $charsPerLine - 1) . '…';
                break;
            }

            $lines[] = mb_substr($remaining, 0, $charsPerLine);
            $remaining = mb_substr($remaining, $charsPerLine);
        }

        return $lines;
    }

    /**
     * MotoHub ブランド表示（右下）
     */
    private function drawBranding(\Intervention\Image\Image $image): void
    {
        $fontPath = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $hasFont = file_exists($fontPath);

        $x = self::WIDTH - 60;
        $y = self::HEIGHT - 40;

        if ($hasFont) {
            $image->text('MotoHub', $x, $y, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(20);
                $font->color('rgba(255, 255, 255, 0.6)');
                $font->align('right');
                $font->valign('bottom');
            });
        } else {
            $image->text('MotoHub', $x, $y, function (FontFactory $font) {
                $font->size(4);
                $font->color('rgba(255, 255, 255, 0.6)');
                $font->align('right');
                $font->valign('bottom');
            });
        }
    }

    private function imageResponse(string $path): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
