<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Bike\ClassRankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * 排気量クラス別「掲載台数ランキング」画像（PNG 1枚）を生成する。
 *
 * 用途: データ系YouTuber等への献上サンプル。インスタDMで送れる画像1枚が成果物。
 * - 既存の x:generate-ranking-image（X投稿用）とは別コマンド。あちらは 1200x675・TOP5・
 *   週次スケジュール稼働中のため触らない。本コマンドは TOP10 を 1080x1080 で独立生成する。
 * - 集計は既存の displacement ランキングと同じ「在庫(is_sold_out=false)を車種別 COUNT・降順」。
 *   排気量帯は site 既存定義 [125]=0-125 / [250]=126-250 / [400]=251-400 / [大型]=401- に合わせる。
 *
 * 実行例:
 *   php artisan motohub:gen-class-ranking --displacement=250 --limit=10
 *   php artisan motohub:gen-class-ranking --displacement=400
 * 生成先: storage/app/public/x-images/class-ranking-{帯}-{YYYY-MM-DD}.png（フルパスを出力）
 */
final class GenerateClassRankingImage extends Command
{
    protected $signature = 'motohub:gen-class-ranking
                            {--displacement=250 : 排気量帯（125/250/400/大型）。site既存区分=250は126-250cc}
                            {--limit=10 : 上位何車種まで（3〜15）}
                            {--min= : 排気量下限ccの上書き（厳密な226-250等にしたい時。空なら帯の既定値）}
                            {--max= : 排気量上限ccの上書き（空なら帯の既定値）}';

    protected $description = '排気量クラス別「掲載台数ランキング」画像(PNG1枚・献上サンプル用)を生成';

    private const WIDTH = 1080;

    private const HEIGHT = 1080;

    private string $fontPath;

    private bool $hasFont;

    public function handle(ClassRankingService $ranking): int
    {
        $this->fontPath = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $this->hasFont = file_exists($this->fontPath);
        if (! $this->hasFont) {
            $this->warn('フォント未検出（NotoSansJP-Bold.ttf）。GDビットマップfontで描画します（日本語が化ける可能性あり）。');
        }

        $input = (string) $this->option('displacement');
        $class = ClassRankingService::normalizeClass($input);
        if ($class === null) {
            $this->error("無効な排気量帯: {$input}（125 / 250 / 400 / 大型）");

            return self::FAILURE;
        }

        $limit = max(3, min(15, (int) $this->option('limit')));
        // --min/--max が指定されたら排気量範囲を上書き（厳密な 226-250 等を出したい時）。
        $min = ($this->option('min') !== null && $this->option('min') !== '') ? (int) $this->option('min') : null;
        $max = ($this->option('max') !== null && $this->option('max') !== '') ? (int) $this->option('max') : null;
        $this->info("対象クラス: {$class} / 上位{$limit}車種 / 在庫(売り切れ除外)".($min !== null || $max !== null ? "（範囲上書き min={$min} max={$max}）" : ''));

        // 集計は画像コマンドとデータAPIで共有（ClassRankingService）。
        $rows = $ranking->classRanking($class, $limit, $min, $max);
        if (count($rows) < 3) {
            $this->error("{$input}クラスのデータが不足しています（".count($rows).'件・最低3件必要）。');

            return self::FAILURE;
        }

        $label = $class === 'large' ? '大型バイク（401cc〜）' : "{$class}ccクラス";
        $path = $this->render($rows, $label, $class);

        $full = Storage::disk('public')->path($path);
        $this->info('掲載台数ランキング画像を生成しました（'.count($rows).'車種）:');
        $this->line($full);
        $this->line('※画像内の台数・相場が実データと一致するか目視確認してください。');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{rank:int,model:string,maker:string,count:int,avg_price_man:?int}>  $rows
     */
    private function render(array $rows, string $label, string $class): string
    {
        $manager = new ImageManager(new GdDriver);
        $image = $manager->create(self::WIDTH, self::HEIGHT);

        $this->drawGradientBackground($image);
        $this->drawAccentLines($image);

        $n = count($rows);
        $this->drawText($image, "{$label} 掲載台数ランキング TOP{$n}", (int) (self::WIDTH / 2), 60, 38, '#ffffff', 'center');
        $this->drawText($image, now()->format('Y年n月').'時点 ・ 中古バイクの掲載台数（在庫数）', (int) (self::WIDTH / 2), 118, 22, '#3b82f6', 'center');
        $this->drawText($image, '出典: MotoHub（motohub.jp）中古バイク掲載データ', (int) (self::WIDTH / 2), 156, 16, '#8a93a6', 'center');

        // 行レイアウト: 上端 startY から rowH 間隔で N 行。フッターの上に収める。
        $startY = 210;
        $bottomLimit = self::HEIGHT - 80; // フッター帯の上端
        $rowH = (int) min(86, (int) (($bottomLimit - $startY) / $n));

        foreach ($rows as $i => $row) {
            $y = $startY + ($i * $rowH);
            $this->drawRow($image, $row, $y);
            if ($i < $n - 1) {
                $this->drawSeparator($image, $y + $rowH - 8);
            }
        }

        // フッター（出典・ブランド明記）
        $this->drawText($image, 'MotoHub（motohub.jp）', (int) (self::WIDTH / 2), self::HEIGHT - 52, 26, '#ffffff', 'center');

        $date = now()->format('Y-m-d');
        $path = "x-images/class-ranking-{$class}-{$date}.png";
        Storage::disk('public')->makeDirectory('x-images');
        Storage::disk('public')->put($path, $image->toPng()->toString());

        return $path;
    }

    /**
     * @param  array{rank:int,model:string,maker:string,count:int,avg_price_man:?int}  $row
     */
    private function drawRow(\Intervention\Image\Image $image, array $row, int $y): void
    {
        $this->drawMedal($image, $row['rank'], 78, $y + 8);

        $this->drawText($image, $row['model'], 150, $y + 2, 28, '#ffffff', 'left');
        if ($row['maker'] !== '') {
            $this->drawText($image, $row['maker'], 150, $y + 40, 15, '#9aa3b2', 'left');
        }

        $this->drawText($image, number_format($row['count']).'台', self::WIDTH - 70, $y + 2, 30, '#3b82f6', 'right');
        if ($row['avg_price_man'] !== null) {
            $this->drawText($image, '平均'.number_format($row['avg_price_man']).'万円', self::WIDTH - 70, $y + 42, 15, '#9aa3b2', 'right');
        }
    }

    private function drawMedal(\Intervention\Image\Image $image, int $rank, int $x, int $y): void
    {
        $color = match ($rank) {
            1 => '#FFD700',
            2 => '#C0C0C0',
            3 => '#CD7F32',
            default => null,
        };

        if ($color !== null) {
            $image->drawCircle($x, $y + 18, function (CircleFactory $c) use ($color) {
                $c->radius(20);
                $c->background($color);
            });
            $this->drawText($image, (string) $rank, $x, $y + 6, 22, '#1a1a2e', 'center');
        } else {
            $this->drawText($image, "{$rank}", $x, $y + 6, 26, '#e67e22', 'center');
        }
    }

    private function drawSeparator(\Intervention\Image\Image $image, int $y): void
    {
        $image->drawLine(function (LineFactory $line) use ($y) {
            $line->from(60, $y);
            $line->to(self::WIDTH - 60, $y);
            $line->color('rgba(255, 255, 255, 0.08)');
        });
    }

    private function drawGradientBackground(\Intervention\Image\Image $image): void
    {
        $r1 = 0x1a; $g1 = 0x1a; $b1 = 0x2e;
        $r2 = 0x16; $g2 = 0x21; $b2 = 0x3e;

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / self::HEIGHT;
            $hex = sprintf(
                '#%02x%02x%02x',
                (int) ($r1 + ($r2 - $r1) * $ratio),
                (int) ($g1 + ($g2 - $g1) * $ratio),
                (int) ($b1 + ($b2 - $b1) * $ratio),
            );
            $image->drawLine(function (LineFactory $line) use ($y, $hex) {
                $line->from(0, $y);
                $line->to(self::WIDTH, $y);
                $line->color($hex);
            });
        }
    }

    private function drawAccentLines(\Intervention\Image\Image $image): void
    {
        for ($i = 0; $i < 4; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, $i);
                $line->to(self::WIDTH, $i);
                $line->color('#e67e22');
            });
        }
        for ($i = 0; $i < 3; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, self::HEIGHT - 1 - $i);
                $line->to(self::WIDTH, self::HEIGHT - 1 - $i);
                $line->color('rgba(230, 126, 34, 0.5)');
            });
        }
    }

    private function drawText(\Intervention\Image\Image $image, string $text, int $x, int $y, int $size, string $color, string $align): void
    {
        if ($this->hasFont) {
            $image->text($text, $x, $y, function (FontFactory $font) use ($size, $color, $align) {
                $font->filename($this->fontPath);
                $font->size($size);
                $font->color($color);
                $font->align($align);
                $font->valign('top');
            });
        } else {
            $gdSize = max(1, min(5, (int) round($size / 10)));
            $image->text($text, $x, $y, function (FontFactory $font) use ($gdSize, $color, $align) {
                $font->size($gdSize);
                $font->color($color);
                $font->align($align);
                $font->valign('top');
            });
        }
    }
}
