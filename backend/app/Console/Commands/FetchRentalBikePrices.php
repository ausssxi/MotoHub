<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalBikePrice;
use App\Support\RentalBike\PriceFetchers\PriceFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 事業者の料金ページから車格別の参考価格を取り込む（第2段階）。
 *
 * ★手動実行（月1回の自動実行は 2-3 で routes/console.php に追加）。
 * ★本実行の前に必ず --dry-run で確認する（news:retitle と同じ進め方）。
 *
 * 取り込み方針・安全策:
 *   - 会社単位で「0件 or 取得例外」なら、その会社の既存行は一切上書きしない（skip）。
 *   - 行単位で、新価格が既存の 1/2 以下 または 2倍以上なら、上書きせずログに残す（異常検知）。
 *   - skip(0件) は Log::error、異常(半分/倍) は Log::warning を出す。★これらは laravel.log に載るので
 *     ops:daily-report（laravel.log の ERROR/WARNING を日次集計）に自動で載る。専用ログは本コマンドの
 *     標準出力を 2-3 のスケジュールで appendOutputTo する。
 */
final class FetchRentalBikePrices extends Command
{
    protected $signature = 'rental-bike:fetch-price
        {company? : 事業者スラッグ（未指定は登録済み全社。当面 yamaha / nirinsho）}
        {--dry-run : DBに書かず、取得件数・新規/更新/skip/異常の内訳を表示するだけ}';

    protected $description = '事業者の料金ページから車格別の参考価格を取得し rental_bike_prices に取り込む';

    /** 異常判定のしきい（前回比）。 */
    private const ANOMALY_LOW = 0.5;  // 半分以下

    private const ANOMALY_HIGH = 2.0; // 倍以上

    public function handle(): int
    {
        /** @var array<string, class-string<PriceFetcher>> $map */
        $map = config('rental_bike.price_fetchers', []);
        if ($map === []) {
            $this->error('料金フェッチャが1社も登録されていません（config/rental_bike.php）。');

            return self::FAILURE;
        }

        $company = (string) ($this->argument('company') ?? '');
        if ($company !== '') {
            if (! isset($map[$company])) {
                $this->error("不明な company: {$company}");
                $this->line('有効なスラッグ: '.implode(' / ', array_keys($map)));

                return self::FAILURE;
            }
            $keys = [$company];
        } else {
            $keys = array_keys($map);
        }

        $dryRun = (bool) $this->option('dry-run');
        $hadFailure = false;

        foreach ($keys as $slug) {
            /** @var PriceFetcher $fetcher */
            $fetcher = app($map[$slug]);
            $this->info("=== [{$slug}] 料金取得".($dryRun ? '（dry-run）' : '').' ===');

            try {
                $rows = $fetcher->fetch();
            } catch (\Throwable $e) {
                // 例外＝取得失敗。既存は上書きしない。ops:daily-report に載るよう ERROR で記録。
                Log::error("[rental-bike:fetch-price] {$slug} 取得に失敗（既存は保持）", ['error' => $e->getMessage()]);
                $this->error("[{$slug}] 取得処理エラー: {$e->getMessage()}（既存は保持）");
                $hadFailure = true;

                continue;
            }

            if ($rows === []) {
                // 0件＝取得失敗の可能性。既存は上書きしない。ops:daily-report に載るよう ERROR で記録。
                Log::error("[rental-bike:fetch-price] {$slug} 0件のため上書きしませんでした（取得失敗の可能性）");
                $this->warn("[{$slug}] 取得0件でした（既存は保持・上書きしません）。");
                $hadFailure = true;

                continue;
            }

            $new = 0;
            $updated = 0;
            $anomaly = 0;
            foreach ($rows as $row) {
                $existing = RentalBikePrice::query()
                    ->where('company_slug', $slug)
                    ->where('vehicle_class', $row['vehicle_class'])
                    ->where('plan', $row['plan'])
                    ->first();

                // 異常検知（前回比・半分以下/倍以上）。上書きせずログに残す。
                if ($existing !== null && $existing->price_yen > 0 && $this->isAnomalous($existing->price_yen, $row['price_yen'])) {
                    Log::warning(sprintf(
                        '[rental-bike:fetch-price] %s %s 料金が大きく変動（前回¥%s→今回¥%s）。上書きせずログのみ',
                        $slug,
                        $row['vehicle_class'],
                        number_format($existing->price_yen),
                        number_format($row['price_yen']),
                    ));
                    $this->warn(sprintf(
                        '  [anomaly] %s: 前回¥%s → 今回¥%s（上書きしません）',
                        $row['vehicle_class'],
                        number_format($existing->price_yen),
                        number_format($row['price_yen']),
                    ));
                    $anomaly++;

                    continue;
                }

                $isNew = $existing === null;
                $isNew ? $new++ : $updated++;

                $priceLabel = ($row['is_from'] ? '¥'.number_format($row['price_yen']).'〜' : '¥'.number_format($row['price_yen']));
                if ($dryRun) {
                    $this->line(sprintf(
                        '  %s %s / %s / %s%s',
                        $isNew ? '[new]   ' : '[update]',
                        $row['vehicle_class'],
                        $priceLabel,
                        $row['plan_label'],
                        $row['note'] !== null ? ' ※'.$row['note'] : '',
                    ));

                    continue;
                }

                $this->save($slug, $fetcher->sourceUrl(), $row);
            }

            $this->info(sprintf('[%s] 取得 %d件（新規 %d / 更新 %d / 異常スキップ %d）', $slug, count($rows), $new, $updated, $anomaly));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('dry-run のため DB へは1件も書き込んでいません。');
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }

    /** 新価格が既存の 1/2 以下 または 2倍以上か。 */
    private function isAnomalous(int $old, int $new): bool
    {
        return $new <= $old * self::ANOMALY_LOW || $new >= $old * self::ANOMALY_HIGH;
    }

    /**
     * 1行を updateOrCreate で保存。
     *
     * @param  array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}  $row
     */
    private function save(string $slug, string $sourceUrl, array $row): void
    {
        RentalBikePrice::updateOrCreate(
            ['company_slug' => $slug, 'vehicle_class' => $row['vehicle_class'], 'plan' => $row['plan']],
            [
                'plan_label' => $row['plan_label'],
                'price_yen' => $row['price_yen'],
                'price_is_from' => $row['is_from'],
                'note' => $row['note'],
                'source_url' => $sourceUrl,
                'fetched_at' => now(),
            ],
        );
    }
}
