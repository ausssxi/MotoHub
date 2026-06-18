<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyBike;

use App\Http\Controllers\Controller;
use App\Models\MyBike;
use App\Services\MyBike\MyBikeService;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * 公開ガレージの動的OGP画像（1200×630）。
 * BlogOgpController と同流儀（Intervention v3 / GD / NotoSansJP / public ディスクへキャッシュ）。
 * 内容: カバー写真（あれば暗くして背景）＋ バイク名 ＋ 走行距離/維持費/平均燃費 ＋ 公開ハンドル ＋ MotoHub。
 * privacy: 非公開ガレージは 404。本名・位置情報は出さない（ハンドルと集計値のみ）。
 */
final class GarageOgpController extends Controller
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const CACHE_DIR = 'ogp/garage/v1';

    public function __construct(
        private readonly MyBikeService $service,
    ) {}

    public function show(MyBike $myBike)
    {
        // 非公開は OGP も出さない（公開ページと同じ防御）
        abort_unless($myBike->is_public, 404);

        $myBike->loadMissing(['user', 'bikeModel.manufacturer', 'fuelLogs', 'maintenanceLogs', 'customRecords', 'images']);
        $stats = $this->service->garageShareStats($myBike);
        $coverPath = $this->coverFilePath($myBike);

        // 表示値が変わったら自動再生成されるよう、内容ハッシュをキャッシュキーにする（更新検知が不要になる）。
        $hash = substr(md5(json_encode($stats).'|'.($coverPath ? (string) @filemtime($coverPath) : 'none')), 0, 16);
        $cachePath = self::CACHE_DIR."/{$myBike->id}/{$hash}.png";

        if (! Storage::disk('public')->exists($cachePath)) {
            // 同一ガレージの古いハッシュを掃除（無限蓄積を防止・件数はガレージ単位で僅少）
            Storage::disk('public')->deleteDirectory(self::CACHE_DIR."/{$myBike->id}");
            $png = $this->generateImage($stats, $coverPath);
            Storage::disk('public')->put($cachePath, $png);
        }

        return response()->file(Storage::disk('public')->path($cachePath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * カバー写真（ギャラリー1枚目）のローカルファイルパス。無ければ null（→写真なしレイアウト）。
     */
    private function coverFilePath(MyBike $myBike): ?string
    {
        $first = $myBike->relationLoaded('images') ? $myBike->images->first() : $myBike->images()->first();
        if (! $first || ! $first->path) {
            return null;
        }

        $disk = Storage::disk(config('garage.image_disk'));

        return $disk->exists($first->path) ? $disk->path($first->path) : null;
    }

    /**
     * @param  array{handle: string, bike_name: string, manufacturer: string, odometer: int, total_cost: int, last12_cost: int, avg_efficiency: ?float}  $stats
     */
    private function generateImage(array $stats, ?string $coverPath): string
    {
        $manager = new ImageManager(new GdDriver);
        $image = $manager->create(self::WIDTH, self::HEIGHT);

        // 背景: カバー写真があれば cover-fit で敷いて暗くする。無ければグラデーション。
        if ($coverPath) {
            try {
                $photo = $manager->read($coverPath)->cover(self::WIDTH, self::HEIGHT);
                $image->place($photo, 'top-left', 0, 0);
                $this->fillRect($image, 0, 0, self::WIDTH, self::HEIGHT, 'rgba(15, 18, 32, 0.5)');
            } catch (\Throwable $e) {
                $this->drawGradientBackground($image); // 壊れた写真でも graceful
            }
        } else {
            $this->drawGradientBackground($image);
        }

        // 下部に黒パネル（数値の可読性確保）＋ 上部オレンジアクセント
        $this->fillRect($image, 0, 430, self::WIDTH, 200, 'rgba(0, 0, 0, 0.55)');
        $this->fillRect($image, 0, 0, self::WIDTH, 6, '#e67e22');

        $hasFont = file_exists($this->fontBold());

        // メーカー（小・オレンジ）
        if ($stats['manufacturer'] !== '') {
            $this->text($image, mb_strtoupper($stats['manufacturer']), 70, 95, 30, '#f0a868', 'left', $hasFont);
        }

        // バイク名（大・白・最大2行）
        $name = $stats['bike_name'];
        $fontSize = mb_strlen($name) <= 14 ? 70 : (mb_strlen($name) <= 22 ? 56 : 46);
        $lines = $this->wrapText($name, $fontSize >= 70 ? 14 : ($fontSize >= 56 ? 18 : 22), 2);
        $y = 140;
        foreach ($lines as $line) {
            $this->text($image, $line, 70, $y, $fontSize, '#ffffff', 'left', $hasFont);
            $y += (int) ($fontSize * 1.25);
        }

        // 公開ハンドル
        $this->text($image, $stats['handle'].' のガレージ', 70, 360, 30, 'rgba(255,255,255,0.85)', 'left', $hasFont);

        // 統計（3カラム）: 走行距離 / 累計維持費 / 平均燃費
        $cols = [
            ['label' => '走行距離', 'value' => number_format($stats['odometer']).' km'],
            ['label' => '累計維持費', 'value' => '¥'.number_format($stats['total_cost'])],
            ['label' => '平均燃費', 'value' => $stats['avg_efficiency'] !== null ? $stats['avg_efficiency'].' km/L' : '—'],
        ];
        $xs = [70, 470, 860];
        foreach ($cols as $i => $col) {
            $this->text($image, $col['label'], $xs[$i], 470, 26, 'rgba(255,255,255,0.7)', 'left', $hasFont);
            $this->text($image, $col['value'], $xs[$i], 505, 50, '#ffffff', 'left', $hasFont);
        }

        // ブランディング（右上）
        $this->text($image, 'MotoHub 愛車ガレージ', self::WIDTH - 60, 95, 28, 'rgba(255,255,255,0.85)', 'right', $hasFont);

        return $image->toPng()->toString();
    }

    private function fontBold(): string
    {
        return storage_path('app/fonts/NotoSansJP-Bold.ttf');
    }

    private function fillRect(Image $image, int $x, int $y, int $w, int $h, string $color): void
    {
        $image->drawRectangle($x, $y, function (RectangleFactory $r) use ($w, $h, $color) {
            $r->size($w, $h);
            $r->background($color);
        });
    }

    private function text(Image $image, string $text, int $x, int $y, int $size, string $color, string $align, bool $hasFont): void
    {
        $fontPath = $this->fontBold();
        $image->text($text, $x, $y, function (FontFactory $font) use ($fontPath, $size, $color, $align, $hasFont) {
            if ($hasFont) {
                $font->filename($fontPath);
                $font->size($size);
            } else {
                $font->size(5); // GD 組み込みフォント（フォント未配置時の graceful）
            }
            $font->color($color);
            $font->align($align);
            $font->valign('top');
        });
    }

    private function drawGradientBackground(Image $image): void
    {
        // 縦グラデーション（#1a1a2e → #16213e）— BlogOgp と同系
        [$r1, $g1, $b1] = [0x1A, 0x1A, 0x2E];
        [$r2, $g2, $b2] = [0x16, 0x21, 0x3E];
        for ($y = 0; $y < self::HEIGHT; $y += 2) {
            $ratio = $y / self::HEIGHT;
            $hex = sprintf('#%02x%02x%02x', (int) ($r1 + ($r2 - $r1) * $ratio), (int) ($g1 + ($g2 - $g1) * $ratio), (int) ($b1 + ($b2 - $b1) * $ratio));
            $this->fillRect($image, 0, $y, self::WIDTH, 2, $hex);
        }
    }

    /**
     * @return array<int, string>
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
            if (count($lines) === $maxLines - 1) {
                $lines[] = mb_substr($remaining, 0, $charsPerLine - 1).'…';
                break;
            }
            $lines[] = mb_substr($remaining, 0, $charsPerLine);
            $remaining = mb_substr($remaining, $charsPerLine);
        }

        return $lines;
    }
}
