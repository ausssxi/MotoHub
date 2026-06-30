<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;

/**
 * shops.service_tags（Webikeのバッジ）から shop_type を導出する。
 *
 * クロール不要・冪等。バッジの捕捉は webike/shop_collector.py が行い、本コマンドは
 * 保存済みの service_tags を読むだけなので、分類ルールを変えても再クロールは不要。
 * 週次のWebike店舗クロール後にスケジュール実行する想定（routes/console.php）。
 *
 * 分類:
 *   dealer       … 販売バッジ（◯◯正規店 / 公取協加盟店）を持つ
 *   repair_only  … 販売バッジ無し かつ 整備バッジ（認証工場 / 修理・点検整備 / 車検受付）あり
 *   unknown      … 上記いずれにも当てはまらない（バッジ無し・サービス系のみ・非Webike等）
 */
class ClassifyShops extends Command
{
    protected $signature = 'shops:classify {--dry-run : 変更を保存せず集計のみ表示}';

    protected $description = 'service_tags（Webikeバッジ）からshop_typeを導出して更新します';

    /** 整備系バッジ（完全一致） */
    private const MAINTENANCE = ['認証工場', '修理・点検整備', '車検受付'];

    /** 販売系バッジ（完全一致） */
    private const SALES_EXACT = ['公取協加盟店'];

    /** 販売系バッジ（接尾辞一致: HONDA正規店 / SUZUKI正規店 / ハスクバーナ正規店 等） */
    private const SALES_SUFFIX = '正規店';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = Shop::count();

        if ($total === 0) {
            $this->info('対象のshopはありません。');

            return self::SUCCESS;
        }

        $this->info("対象: {$total} shops".($dryRun ? '（dry-run）' : ''));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $counts = ['dealer' => 0, 'repair_only' => 0, 'unknown' => 0];
        $changed = 0;

        Shop::select('id', 'service_tags', 'shop_type')->chunkById(500, function ($shops) use ($dryRun, $bar, &$counts, &$changed) {
            foreach ($shops as $shop) {
                $type = $this->classify($shop->service_tags);
                $counts[$type]++;

                if ($shop->shop_type !== $type) {
                    $changed++;
                    if (! $dryRun) {
                        // updated_at を動かさない（Meili再索引フラグ等への波及を避ける）
                        $shop->updateQuietly(['shop_type' => $type]);
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info(sprintf(
            '完了: dealer=%d, repair_only=%d, unknown=%d（変更=%d%s）',
            $counts['dealer'],
            $counts['repair_only'],
            $counts['unknown'],
            $changed,
            $dryRun ? ' / 未保存' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * バッジ配列から shop_type を判定する。
     *
     * @param  array<int, string>|null  $tags
     */
    public function classify(?array $tags): string
    {
        if (empty($tags)) {
            return 'unknown';
        }

        $hasSales = false;
        $hasMaintenance = false;

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            if (in_array($tag, self::SALES_EXACT, true) || str_ends_with($tag, self::SALES_SUFFIX)) {
                $hasSales = true;
            }
            if (in_array($tag, self::MAINTENANCE, true)) {
                $hasMaintenance = true;
            }
        }

        if ($hasSales) {
            return 'dealer';
        }
        if ($hasMaintenance) {
            return 'repair_only';
        }

        return 'unknown';
    }
}
