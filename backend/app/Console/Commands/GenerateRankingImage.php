<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeModelMarketStat;
use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\CircleFactory;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

final class GenerateRankingImage extends Command
{
    protected $signature = 'x:generate-ranking-image
                            {--type=weekly-sales : weekly-sales|bargains|prefecture|budget|fast-selling|price-up|displacement}
                            {--prefecture= : 都道府県名（type=prefectureの場合必須）}
                            {--displacement= : 排気量帯（125/250/400/大型）}
                            {--dry-run : 画像生成のみ、パスを表示}';

    protected $description = 'X投稿用ランキング画像を自動生成';

    private const WIDTH = 1200;
    private const HEIGHT = 675;
    private const DISCOUNT_THRESHOLD = 0.8;

    private string $fontPath;
    private bool $hasFont;

    public function handle(): int
    {
        $this->fontPath = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $this->hasFont = file_exists($this->fontPath);

        $type = $this->option('type');

        if (! in_array($type, ['weekly-sales', 'bargains', 'prefecture', 'budget', 'fast-selling', 'price-up', 'displacement'], true)) {
            $this->error("無効なタイプ: {$type}（weekly-sales|bargains|prefecture|budget|fast-selling|price-up|displacement）");

            return self::FAILURE;
        }

        if ($type === 'prefecture' && ! $this->option('prefecture')) {
            $this->error('--prefecture オプションが必須です（例: --prefecture=東京都）');

            return self::FAILURE;
        }

        $path = match ($type) {
            'weekly-sales' => $this->handleWeeklySales(),
            'bargains' => $this->handleBargains(),
            'prefecture' => $this->handlePrefecture($this->option('prefecture')),
            'budget' => $this->handleBudget(),
            'fast-selling' => $this->handleFastSelling(),
            'price-up' => $this->handlePriceUp(),
            'displacement' => $this->handleDisplacement($this->option('displacement')),
        };

        if ($path === null) {
            return self::FAILURE;
        }

        $fullPath = Storage::disk('public')->path($path);
        $this->info("画像を生成しました: {$fullPath}");

        return self::SUCCESS;
    }

    private function handleWeeklySales(): ?string
    {
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', now()->subWeek())
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn('データ不足のためスキップ（最低3件必要）');

            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $marketStats = BikeModelMarketStat::whereIn('bike_model_id', $rankings->pluck('bike_model_id'))
            ->pluck('avg_price', 'bike_model_id');

        $rows = [];
        foreach ($rankings->values() as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $avgPrice = $marketStats[$row->bike_model_id] ?? null;
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => $row->sold_count . '台',
                'rightSubText' => $avgPrice ? '¥' . number_format((int) round($avgPrice / 10000)) . '万円' : '',
                'rightColor' => '#2ecc71',
            ];
        }

        $startDate = now()->subWeek()->format('n/j');
        $endDate = now()->format('n/j');

        return $this->generateWeeklySalesImage($rows, "{$startDate}〜{$endDate}");
    }

    private function handleBargains(): ?string
    {
        $excludedModelIds = BikeModel::where('name', '他車種')->pluck('id');

        $modelAverages = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotIn('bike_model_id', $excludedModelIds)
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 3)
            ->pluck('avg_price', 'bike_model_id');

        $listings = Listing::with(['bikeModel.manufacturer'])
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelAverages->keys())
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $bargains = [];
        foreach ($listings as $listing) {
            $avgPrice = (float) $modelAverages[$listing->bike_model_id];
            if ($avgPrice <= 0) {
                continue;
            }

            if ($listing->total_price < ($avgPrice * self::DISCOUNT_THRESHOLD)) {
                $percentOff = (int) round((($avgPrice - $listing->total_price) / $avgPrice) * 100);
                $bargains[] = [
                    'name' => $listing->bikeModel?->displayLabel() ?? '不明',
                    'maker' => $listing->bikeModel?->manufacturer?->name ?? '',
                    'price' => $listing->total_price,
                    'avg_price' => (int) round($avgPrice),
                    'percent_off' => $percentOff,
                ];
            }
        }

        usort($bargains, fn ($a, $b) => $b['percent_off'] <=> $a['percent_off']);

        if (count($bargains) < 3) {
            $this->warn('お買い得データ不足のためスキップ（最低3件必要）');

            return null;
        }

        $rows = [];
        foreach (array_slice($bargains, 0, 3) as $i => $item) {
            $rows[] = [
                'rank' => $i + 1,
                'name' => $item['name'],
                'maker' => $item['maker'],
                'percent_off' => $item['percent_off'],
                'price' => $item['price'],
                'avg_price' => $item['avg_price'],
            ];
        }

        return $this->generateBargainsImage($rows);
    }

    private function handlePrefecture(string $prefecture): ?string
    {
        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', $prefecture)
            ->select('listings.bike_model_id', DB::raw('COUNT(*) as listing_count'))
            ->groupBy('listings.bike_model_id')
            ->orderByDesc('listing_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn("「{$prefecture}」のデータ不足のためスキップ（最低3件必要）");

            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rankings->values() as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => $row->listing_count . '台',
                'rightColor' => '#00bcd4',
            ];
        }

        return $this->generatePrefectureImage($rows, $prefecture);
    }

    private function handleBudget(): ?string
    {
        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '<=', 100000)
            ->select('bike_model_id', DB::raw('COUNT(*) as stock_count'), DB::raw('MIN(total_price) as min_price'))
            ->groupBy('bike_model_id')
            ->orderByDesc('stock_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn('10万円以下データ不足のためスキップ（最低3件必要）');

            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rankings->values() as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $minPriceText = '¥' . number_format((int) $row->min_price) . '〜';
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => $minPriceText,
                'rightSubText' => $row->stock_count . '台在庫',
                'rightColor' => '#22c55e',
            ];
        }

        return $this->generateBudgetImage($rows);
    }

    private function handleFastSelling(): ?string
    {
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', now()->subDays(30))
            ->whereNotNull('bike_model_id')
            ->selectRaw('bike_model_id, AVG(DATEDIFF(updated_at, created_at)) as avg_days, COUNT(*) as sold_count')
            ->groupBy('bike_model_id')
            ->having('sold_count', '>=', 3)
            ->havingRaw('AVG(DATEDIFF(updated_at, created_at)) BETWEEN 1 AND 365')
            ->orderBy('avg_days')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn('即売れデータ不足のためスキップ（最低3件必要）');

            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rankings->values() as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $avgDays = (int) round($row->avg_days);
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => "平均{$avgDays}日で売却",
                'rightSubText' => "先月{$row->sold_count}台売却",
                'rightColor' => '#a855f7',
            ];
        }

        return $this->generateFastSellingImage($rows);
    }

    private function handlePriceUp(): ?string
    {
        $thisMonth = Listing::where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 5)
            ->get()
            ->keyBy('bike_model_id');

        $lastMonth = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'))
            ->groupBy('bike_model_id')
            ->get()
            ->keyBy('bike_model_id');

        $priceChanges = [];
        foreach ($thisMonth as $modelId => $current) {
            $previous = $lastMonth->get($modelId);
            if (! $previous || $previous->avg_price <= 0) {
                continue;
            }

            $changePercent = (($current->avg_price - $previous->avg_price) / $previous->avg_price) * 100;
            if ($changePercent > 0) {
                $priceChanges[] = [
                    'bike_model_id' => $modelId,
                    'change_percent' => $changePercent,
                    'current_avg' => $current->avg_price,
                ];
            }
        }

        usort($priceChanges, fn ($a, $b) => $b['change_percent'] <=> $a['change_percent']);

        if (count($priceChanges) < 3) {
            $this->warn('値上がりデータ不足のためスキップ（最低3件必要）');

            return null;
        }

        $topChanges = array_slice($priceChanges, 0, 5);
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', array_column($topChanges, 'bike_model_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach (array_values($topChanges) as $i => $item) {
            $model = $models->get($item['bike_model_id']);
            $percentText = '+' . number_format($item['change_percent'], 1) . '%';
            $avgPriceText = '平均' . number_format((int) round($item['current_avg'] / 10000)) . '万円';
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => $percentText,
                'rightSubText' => $avgPriceText,
                'rightColor' => '#ef4444',
            ];
        }

        return $this->generatePriceUpImage($rows);
    }

    private function handleDisplacement(?string $displacement): ?string
    {
        $ranges = [
            '125' => [0, 125],
            '250' => [126, 250],
            '400' => [251, 400],
            '大型' => [401, null],
        ];

        if ($displacement === null) {
            $keys = array_keys($ranges);
            $weekNumber = (int) now()->format('W');
            $displacement = $keys[$weekNumber % count($keys)];
            $this->info("排気量帯を自動選択: {$displacement}（週番号{$weekNumber}）");
        }

        if (! isset($ranges[$displacement])) {
            $this->error("無効な排気量帯: {$displacement}（125/250/400/大型）");

            return null;
        }

        [$min, $max] = $ranges[$displacement];

        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->displacementBetween($min, $max)
            ->select('bike_model_id', DB::raw('COUNT(*) as stock_count'), DB::raw('AVG(total_price) as avg_price'))
            ->groupBy('bike_model_id')
            ->orderByDesc('stock_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            $this->warn("{$displacement}ccクラスのデータ不足のためスキップ（最低3件必要）");

            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($rankings->values() as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $avgPriceText = $row->avg_price ? number_format((int) round($row->avg_price / 10000)) . '万円' : '';
            $rows[] = [
                'rank' => $i + 1,
                'name' => $model?->displayLabel() ?? '不明',
                'maker' => $model?->manufacturer?->name ?? '',
                'rightText' => $row->stock_count . '台',
                'rightSubText' => $avgPriceText,
                'rightColor' => '#3b82f6',
            ];
        }

        $label = $displacement === '大型' ? '大型（401cc〜）' : "{$displacement}ccクラス";

        return $this->generateDisplacementImage($rows, $label, $displacement);
    }

    // ─── Image Generation ───────────────────────────────────────────

    private function generateWeeklySalesImage(array $rows, string $dateRange): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, '今週の売れ筋バイクTOP5', 60);
        $this->drawSubtitle($image, $dateRange, 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], $row['rightSubText'] ?? '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, 'weekly-sales');
    }

    private function generateBargainsImage(array $rows): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, '今週のお買い得バイクTOP3', 60);
        $this->drawSubtitle($image, now()->format('Y年n月j日') . ' 時点', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 150);

            $this->drawRankMedal($image, $row['rank'], 80, $y + 20);

            // 割引率
            $this->drawText($image, $row['percent_off'] . '% OFF', 80, $y + 65, 32, '#e74c3c', 'left');

            // 車種名 + メーカー
            $this->drawText($image, $row['name'], 280, $y + 10, 28, '#ffffff', 'left');
            $this->drawText($image, $row['maker'], 280, $y + 50, 16, '#999999', 'left');

            // 価格
            $priceText = '¥' . number_format((int) round($row['price'] / 10000)) . '万円';
            $avgText = '相場 ¥' . number_format((int) round($row['avg_price'] / 10000)) . '万円';
            $this->drawText($image, $priceText, self::WIDTH - 80, $y + 10, 28, '#2ecc71', 'right');
            $this->drawText($image, $avgText, self::WIDTH - 80, $y + 50, 16, '#777777', 'right');

            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 130);
            }
        }

        return $this->saveImage($image, 'bargains');
    }

    private function generatePrefectureImage(array $rows, string $prefecture): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, "{$prefecture}で人気のバイクTOP5", 60);
        $this->drawSubtitle($image, now()->format('Y年n月j日') . ' 在庫数ランキング', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, 'prefecture');
    }

    private function generateBudgetImage(array $rows): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, '10万円以下で買えるバイクTOP5', 60, '#22c55e');
        $this->drawSubtitle($image, now()->format('Y年n月j日') . ' 時点', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], $row['rightSubText'] ?? '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, 'budget');
    }

    private function generateFastSellingImage(array $rows): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, '即売れバイクTOP5', 60, '#a855f7');
        $this->drawSubtitle($image, '直近30日間の売却速度ランキング', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], $row['rightSubText'] ?? '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, 'fast-selling');
    }

    private function generatePriceUpImage(array $rows): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, '今月値上がりしたバイクTOP5', 60, '#ef4444');
        $this->drawSubtitle($image, now()->format('Y年n月') . ' 前月比', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], $row['rightSubText'] ?? '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, 'price-up');
    }

    private function generateDisplacementImage(array $rows, string $label, string $displacement): string
    {
        $image = $this->createBaseImage();

        $this->drawTitle($image, "{$label} 人気バイクTOP5", 60, '#3b82f6');
        $this->drawSubtitle($image, now()->format('Y年n月j日') . ' 在庫数ランキング', 100);

        foreach ($rows as $i => $row) {
            $y = 160 + ($i * 80);
            $this->drawRankingRow($image, $row['rank'], $row['name'], $row['maker'], $row['rightText'], $row['rightSubText'] ?? '', $y, $row['rightColor']);
            if ($i < count($rows) - 1) {
                $this->drawSeparatorLine($image, $y + 70);
            }
        }

        return $this->saveImage($image, "displacement-{$displacement}");
    }

    // ─── Drawing Helpers ────────────────────────────────────────────

    private function createBaseImage(): \Intervention\Image\Image
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->create(self::WIDTH, self::HEIGHT);

        $this->drawGradientBackground($image);
        $this->drawAccentLines($image);
        $this->drawBranding($image);

        return $image;
    }

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

    private function drawAccentLines(\Intervention\Image\Image $image): void
    {
        for ($i = 0; $i < 4; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, $i);
                $line->to(self::WIDTH, $i);
                $line->color('#e67e22');
            });
        }

        for ($i = 0; $i < 2; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i) {
                $line->from(0, self::HEIGHT - 1 - $i);
                $line->to(self::WIDTH, self::HEIGHT - 1 - $i);
                $line->color('rgba(230, 126, 34, 0.5)');
            });
        }
    }

    private function drawBranding(\Intervention\Image\Image $image): void
    {
        $x = self::WIDTH - 60;
        $y = self::HEIGHT - 40;

        if ($this->hasFont) {
            $image->text('MotoHub', $x, $y, function (FontFactory $font) {
                $font->filename($this->fontPath);
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

    private function drawTitle(\Intervention\Image\Image $image, string $text, int $y, string $color = '#ffffff'): void
    {
        $this->drawText($image, $text, (int) (self::WIDTH / 2), $y, 36, $color, 'center');
    }

    private function drawSubtitle(\Intervention\Image\Image $image, string $text, int $y): void
    {
        $this->drawText($image, $text, (int) (self::WIDTH / 2), $y, 18, '#999999', 'center');
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

    private function drawRankMedal(\Intervention\Image\Image $image, int $rank, int $x, int $y): void
    {
        $medalColor = match ($rank) {
            1 => '#FFD700',
            2 => '#C0C0C0',
            3 => '#CD7F32',
            default => null,
        };

        if ($medalColor !== null) {
            $image->drawCircle($x, $y + 16, function (CircleFactory $circle) use ($medalColor) {
                $circle->radius(20);
                $circle->background($medalColor);
            });
            $this->drawText($image, (string) $rank, $x, $y + 4, 24, '#ffffff', 'center');
        } else {
            $this->drawText($image, "{$rank}.", $x, $y, 28, '#e67e22', 'center');
        }
    }

    private function drawRankingRow(\Intervention\Image\Image $image, int $rank, string $name, string $maker, string $rightText, string $rightSubText, int $y, string $rightColor): void
    {
        $this->drawRankMedal($image, $rank, 80, $y + 10);

        // 車種名
        $this->drawText($image, $name, 160, $y + 5, 28, '#ffffff', 'left');
        // メーカー名
        if ($maker) {
            $this->drawText($image, $maker, 160, $y + 42, 16, '#999999', 'left');
        }

        // 右側テキスト
        $this->drawText($image, $rightText, self::WIDTH - 80, $y + 5, 24, $rightColor, 'right');
        if ($rightSubText) {
            $this->drawText($image, $rightSubText, self::WIDTH - 80, $y + 38, 16, '#999999', 'right');
        }
    }

    private function drawSeparatorLine(\Intervention\Image\Image $image, int $y): void
    {
        $image->drawLine(function (LineFactory $line) use ($y) {
            $line->from(60, $y);
            $line->to(self::WIDTH - 60, $y);
            $line->color('rgba(255, 255, 255, 0.1)');
        });
    }

    private function saveImage(\Intervention\Image\Image $image, string $type): string
    {
        $date = now()->format('Y-m-d');
        $path = "x-images/{$type}-{$date}.png";

        Storage::disk('public')->makeDirectory('x-images');
        Storage::disk('public')->put($path, $image->toPng()->toString());

        return $path;
    }
}
