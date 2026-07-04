<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use App\Support\ShopNameNormalizer;
use Illuminate\Console\Command;

/**
 * shops.name_normalized のバックフィル。
 *
 * スクレイパー（SQLAlchemy）は Eloquent を通らないため name_normalized が NULL で入る。
 * 正規化ロジックを2言語に重複実装するとドリフトするため、Python側は触らず
 * このコマンドを日次で回して NULL 行・不整合行を埋める。冪等（全件再計算しても安全）。
 */
final class NormalizeShopNames extends Command
{
    protected $signature = 'shops:normalize-names';

    protected $description = 'shops.name_normalized を ShopNameNormalizer で埋める（NULL/不整合行のみ更新）';

    public function handle(): int
    {
        $updated = 0;

        Shop::query()
            ->select(['id', 'name', 'name_normalized'])
            ->orderBy('id')
            ->chunkById(500, function ($shops) use (&$updated) {
                foreach ($shops as $shop) {
                    $normalized = ShopNameNormalizer::normalize((string) $shop->name);
                    if ($shop->name_normalized === $normalized) {
                        continue; // 冪等: 既に一致していれば書き込まない
                    }
                    // mutator を経由せず normalized 列だけ更新（name の再セット不要）
                    Shop::whereKey($shop->id)->update(['name_normalized' => $normalized]);
                    $updated++;
                }
            });

        $this->info("shops.name_normalized 更新: {$updated} 件");

        return self::SUCCESS;
    }
}
