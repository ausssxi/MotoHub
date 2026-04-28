<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ParseParkingRates extends Command
{
    protected $signature = 'parking:parse-rates
        {--dry-run : パース結果を表示するだけで保存しない}
        {--limit=0 : 処理件数の上限（0=無制限）}';

    protected $description = 'price_detailテキストから料金を正規表現でパースし、既存のINTカラムを補完';

    private int $updated = 0;
    private int $skipped = 0;
    private int $unparsed = 0;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $query = BikeParking::where('is_active', true)
            ->whereNotNull('price_detail')
            ->where('price_detail', '!=', '');

        $total = $query->count();
        $this->info("対象: {$total} 件（price_detail有り）");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $query->chunk(500, function ($parkings) use ($isDryRun, $limit, &$processed) {
            foreach ($parkings as $parking) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }
                $this->processParking($parking, $isDryRun);
                $processed++;
            }
            if ($limit > 0 && $processed >= $limit) {
                return false;
            }
        });

        $this->newLine();
        $this->info("完了: 更新 {$this->updated}件 / スキップ(変更なし) {$this->skipped}件 / パース不能 {$this->unparsed}件");

        return self::SUCCESS;
    }

    private function processParking(BikeParking $parking, bool $isDryRun): void
    {
        $text = $parking->price_detail;
        $normalized = $this->normalize($text);

        $hourly = $this->parseHourly($normalized);
        $daily = $this->parseDaily($normalized);
        $monthly = $this->parseMonthly($normalized);

        // 既にINTカラムに値がある場合は上書きしない
        $updates = [];
        if ($hourly !== null && !$parking->price_per_hour) {
            $updates['price_per_hour'] = $hourly;
        }
        if ($daily !== null && !$parking->price_per_day) {
            $updates['price_per_day'] = $daily;
        }
        if ($monthly !== null && !$parking->price_per_month) {
            $updates['price_per_month'] = $monthly;
        }

        if (empty($updates)) {
            $this->skipped++;
            return;
        }

        if ($isDryRun) {
            $parts = [];
            foreach ($updates as $col => $val) {
                $parts[] = "{$col}={$val}";
            }
            $this->line("  [{$parking->id}] \"{$text}\" → " . implode(', ', $parts));
            $this->updated++;
            return;
        }

        $parking->update($updates);
        $this->updated++;
    }

    /**
     * テキストの正規化: 全角→半角数字、カンマ除去
     */
    private function normalize(string $text): string
    {
        // 全角数字→半角
        $text = mb_convert_kana($text, 'n');
        // 全角カンマ・半角カンマ除去（数値内）
        $text = preg_replace('/(\d),(\d)/', '$1$2', $text);
        // 全角チルダ・波ダッシュを統一
        $text = str_replace(['〜', '～'], '~', $text);
        // ￥記号 → 円 に統一（全角・半角両方）
        $text = preg_replace('/(?:￥|¥)\s*(\d+)/u', '$1円', $text);

        return $text;
    }

    /**
     * 時間料金のパース（円/時に換算）
     */
    private function parseHourly(string $text): ?int
    {
        // "最初のX時間無料。無料時間含めX時間ごとにYYY円" → YYY/X 円/時
        if (preg_match('/無料時間含め(\d+)時間\s*(?:ごと|毎)(?:に)?\s*(\d+)円/', $text, $m)) {
            $hours = (int) $m[1];
            $price = (int) $m[2];
            if ($hours > 0) {
                return (int) round($price / $hours);
            }
        }

        // "最初のX時間無料以後XX分毎YYY円" → XX分YYY円として換算
        if (preg_match('/無料\s*(?:以後|以降|後)\s*(\d+)分\s*(?:毎|ごと)(?:に)?\s*(\d+)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0 && $minutes <= 120) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XXX円/時" or "XXX円/1時間"
        if (preg_match('/(\d+)円\/時/', $text, $m)) {
            return (int) $m[1];
        }

        // "1時間XXX円" or "60分XXX円"
        if (preg_match('/(?:1時間|60分)\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/X時間" → 時間あたりに換算
        if (preg_match('/(\d+)円\/(\d+)時間/', $text, $m)) {
            $price = (int) $m[1];
            $hours = (int) $m[2];
            if ($hours > 0) {
                return (int) round($price / $hours);
            }
        }

        // "XX分毎YYY円" or "XX分ごとにYYY円"
        if (preg_match('/(\d+)分\s*(?:毎|ごと)(?:に)?\s*(\d+)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0 && $minutes <= 120 && $price > 0) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XX時間ごとにYYY円" or "XX時間毎YYY円"
        if (preg_match('/(\d+)時間\s*(?:毎|ごと)(?:に)?\s*(\d+)円/', $text, $m)) {
            $hours = (int) $m[1];
            $price = (int) $m[2];
            if ($hours > 0) {
                return (int) round($price / $hours);
            }
        }

        // "XX分YYY円" → 時間あたりに換算（最初にマッチしたもの）
        // ただし "24時間最大" や "当日最大" の前は除外
        if (preg_match('/(?<!最大|入庫)(\d+)分\s*(\d+)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0 && $minutes <= 120 && $price > 0) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XXX円/XX分"
        if (preg_match('/(\d+)円\/(\d+)分/', $text, $m)) {
            $price = (int) $m[1];
            $minutes = (int) $m[2];
            if ($minutes > 0) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XX分/YYY円" （分/円の逆順）
        if (preg_match('/(\d+)分\/(\d+)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0 && $minutes <= 120 && $price > 0) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XXX円 / XX分" （スペース入りスラッシュ）
        if (preg_match('/(\d+)円\s*\/\s*(\d+)分/', $text, $m)) {
            $price = (int) $m[1];
            $minutes = (int) $m[2];
            if ($minutes > 0 && $minutes <= 120) {
                return (int) round($price * 60 / $minutes);
            }
        }

        // "XXX円/XH" （大文字H）
        if (preg_match('/(\d+)円\/(\d+)H/i', $text, $m)) {
            $price = (int) $m[1];
            $hours = (int) $m[2];
            if ($hours > 0 && $hours <= 4) {
                return (int) round($price / $hours);
            }
        }

        return null;
    }

    /**
     * 日額料金のパース
     */
    private function parseDaily(string $text): ?int
    {
        // "XXX円/日" or "XXX円/1日"
        if (preg_match('/(\d+)円\/(?:1)?日/', $text, $m)) {
            return (int) $m[1];
        }

        // "1日XXX円"
        if (preg_match('/1日\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "24時間最大XXX円" or "当日最大XXX円"
        if (preg_match('/(?:24時間|当日)\s*最大\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "最大料金　入庫当日24時まで　XXX円" or "最大料金：駐車からXX時間まで XXX円" or "最大料金 XXX円"
        if (preg_match('/最大料金\s*[:：]?\s*(?:[^円]*?まで\s*)?(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "入庫から24時間まで XXX円"
        if (preg_match('/24時間\s*(?:まで)?\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/24h" or "XXX円/24時間"
        if (preg_match('/(\d+)円\/24[h時間]/', $text, $m)) {
            return (int) $m[1];
        }

        // "平日XXX円/Xh" (時間指定の日額料金)
        if (preg_match('/平日\s*(\d+)円\/\d+h/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/Xh" (8h以上は日額とみなす)
        if (preg_match('/(\d+)円\/(\d+)h/', $text, $m)) {
            $price = (int) $m[1];
            $hours = (int) $m[2];
            if ($hours >= 8) {
                return $price;
            }
        }

        // "XXX円/1回1日" or "XXX円/1回"（1回利用＝日額）
        if (preg_match('/(\d+)円\/1回/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/回"
        if (preg_match('/(\d+)円\/回/', $text, $m)) {
            return (int) $m[1];
        }

        // "時間帯最大　X時~X時 YYY円" → 最安値を日額とする
        if (preg_match_all('/時間帯?最大\s*(?:\d+時~\d+時)?\s*(\d+)円/u', $text, $matches)) {
            return (int) min(array_map('intval', $matches[1]));
        }

        // "12時間；125cc超：YYY円、125cc以下ZZZ円" → 安い方を日額
        if (preg_match('/\d+時間[；;].+?(\d+)円.*?(\d+)円/u', $text, $m)) {
            return (int) min((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/\d+時間[；;].*?(\d+)円/u', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/日" の全角スラッシュ対応 "XXX円／日"
        if (preg_match('/(\d+)円[／\/]日/', $text, $m)) {
            return (int) $m[1];
        }

        // "X時間まで YYY円" (8h以上は日額)
        if (preg_match('/(\d+)時間まで\s*(\d+)円/', $text, $m)) {
            $hours = (int) $m[1];
            $price = (int) $m[2];
            if ($hours >= 8) {
                return $price;
            }
        }

        // "1日1回につきXXX円"
        if (preg_match('/1日1回\s*(?:につき)?\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円（YYY円）/日" or "：XXX円（YYY円）/日" → 安い方（屋根無し等）
        if (preg_match('/(\d+)円[（(](\d+)円[）)][\/／]日/u', $text, $m)) {
            return (int) min((int) $m[1], (int) $m[2]);
        }

        // "：XXX円/日" （コロンの後に料金）
        if (preg_match('/[:：]\s*(\d+)円[\/／]日/u', $text, $m)) {
            return (int) $m[1];
        }

        // "止め放題料金　平日：XXX円" → 日額
        if (preg_match('/止め放題.*?(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円／X時間毎" (全角スラッシュ、8h以上は日額)
        if (preg_match('/(\d+)円[／\/](\d+)時間(?:毎|ごと)/u', $text, $m)) {
            $price = (int) $m[1];
            $hours = (int) $m[2];
            if ($hours >= 8) {
                return $price;
            }
        }

        return null;
    }

    /**
     * 月極料金のパース
     */
    private function parseMonthly(string $text): ?int
    {
        // "月極: なし" or "月額: なし" → 月極なし、スキップ
        if (preg_match('/月[極額]\s*[:：]?\s*なし/', $text)) {
            return null;
        }

        // "月極XXX円" or "月額XXX円" or "月極:XXX円" or "月極: XXX円"
        if (preg_match('/月[極額]\s*[:：]?\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "XXX円/月"
        if (preg_match('/(\d+)円\/月/', $text, $m)) {
            return (int) $m[1];
        }

        // "30日間XXX円" or "1ヶ月XXX円" or "1ヵ月XXX円"
        if (preg_match('/(?:30日間|1[ヶヵか]月)\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        // "【1ヵ月】XXX円"
        if (preg_match('/【1[ヶヵか]月】\s*(\d+)円/', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
