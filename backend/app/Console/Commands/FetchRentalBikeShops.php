<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalBikeShop;
use App\Support\RentalBike\ShopFetcher;
use Illuminate\Console\Command;

/**
 * レンタルバイク事業者サイトから「店舗の事実情報のみ」を取り込む（複数事業者横断の材料集め）。
 *
 * ★手動実行のみ（cron に載せない）。★画像・紹介文・在庫は取得しない・カラムも作らない。
 * ★本実行の前に必ず --dry-run の結果を確認する（news:retitle と同じ進め方）。
 *
 * 取り込み方針:
 *   - 照合は dedup_key（(company_slug, external_id) or (company_slug, name, address) の sha1）1本。
 *     同じ店舗を2回取り込んでも updateOrCreate で重複しない。
 *   - is_active は「非公開方向」だけ自動化する。更新時は is_active を上書きしない（手動の非公開判断を守る）。
 *     全社フル取得（company 未指定・非 dry-run）に限り、今回出現しなかった行を is_active=false へ落とす（削除はしない）。
 */
final class FetchRentalBikeShops extends Command
{
    protected $signature = 'rental-bike:fetch
        {company? : 事業者スラッグ（未指定は登録済み全社）}
        {--dry-run : DBに書かず、取得件数・新規/更新の内訳を表示するだけ}';

    protected $description = 'レンタルバイク事業者サイトから店舗の事実情報を取得し rental_bike_shops に取り込む';

    public function handle(): int
    {
        /** @var array<string, class-string<ShopFetcher>> $map */
        $map = config('rental_bike.fetchers', []);
        if ($map === []) {
            $this->error('Fetcher が1社も登録されていません（config/rental_bike.php）。');

            return self::FAILURE;
        }

        $company = $this->argument('company');
        if ($company !== null && $company !== '') {
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
        $fullRun = ! $dryRun && ($company === null || $company === '');

        $hadFailure = false;

        foreach ($keys as $key) {
            /** @var ShopFetcher $fetcher */
            $fetcher = app($map[$key]);
            $slug = $fetcher->slug();
            $label = $fetcher->company();

            $this->info("=== [{$label}] 取得開始".($dryRun ? '（dry-run）' : '').' ===');

            try {
                $rows = $fetcher->fetch();
            } catch (\Throwable $e) {
                $this->error("[{$label}] 取得処理エラー: {$e->getMessage()}");
                $hadFailure = true;

                continue;
            }

            if ($rows === []) {
                $this->warn("[{$label}] 取得0件でした。");
                $hadFailure = true;

                continue;
            }

            $new = 0;
            $updated = 0;
            $prefNull = 0;
            $seenKeys = [];

            foreach ($rows as $row) {
                // ★保険: 万一 Fetcher が画像系キーを混ぜても DB へ持ち込まない。
                $this->assertNoImageKeys($row, $label);

                $dedupKey = RentalBikeShop::makeDedupKey(
                    $slug,
                    $row['external_id'] ?? null,
                    (string) ($row['name'] ?? ''),
                    (string) ($row['address'] ?? ''),
                );
                $seenKeys[] = $dedupKey;

                if (($row['prefecture'] ?? null) === null) {
                    $prefNull++;
                }

                $existing = RentalBikeShop::where('dedup_key', $dedupKey)->first();
                $isNew = $existing === null;
                $isNew ? $new++ : $updated++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  %s %s / %s%s / TEL:%s',
                        $isNew ? '[new]   ' : '[update]',
                        $row['name'] ?? '',
                        $row['prefecture'] ?? '(都道府県不明)',
                        $row['city'] ?? '',
                        $row['tel'] ?? '-',
                    ));

                    continue;
                }

                $this->save($slug, $label, $dedupKey, $row);
            }

            $this->info(sprintf(
                '[%s] 取得 %d件（新規 %d / 更新 %d / 都道府県不明 %d）',
                $label,
                count($rows),
                $new,
                $updated,
                $prefNull,
            ));

            // 全社フル取得のときだけ、今回出現しなかった行を非公開化する（削除はしない）。
            // ★自動化は「非公開方向」のみ。公開（is_active=true への復帰）は人が判断する。
            if ($fullRun) {
                $deactivated = RentalBikeShop::query()
                    ->where('company_slug', $slug)
                    ->where('is_active', true)
                    ->whereNotIn('dedup_key', $seenKeys)
                    ->update(['is_active' => false]);
                if ($deactivated > 0) {
                    $this->warn("[{$label}] 今回未出現のため非公開化: {$deactivated}件（削除はしていません）");
                }
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('dry-run のため DB へは1件も書き込んでいません。');
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 1店舗を updateOrCreate で保存。
     * ★更新時は is_active を上書きしない（手動の非公開判断を守る）。新規は DB 既定の is_active=1。
     *
     * @param  array<string, mixed>  $row
     */
    private function save(string $slug, string $label, string $dedupKey, array $row): void
    {
        RentalBikeShop::updateOrCreate(
            ['dedup_key' => $dedupKey],
            [
                'company' => $label,
                'company_slug' => $slug,
                'external_id' => $row['external_id'] ?? null,
                'name' => $row['name'] ?? '',
                'postal_code' => $row['postal_code'] ?? null,
                'address' => $row['address'] ?? '',
                'prefecture' => $row['prefecture'] ?? null,
                'city' => $row['city'] ?? null,
                'tel' => $row['tel'] ?? null,
                'opening_hours' => $row['opening_hours'] ?? null,
                'official_url' => $row['official_url'] ?? null,
                'fetched_at' => now(),
            ],
        );
    }

    /**
     * ★画像系キーが混入していないことを保証する（写真・ロゴは扱わない方針の実装ガード）。
     *
     * @param  array<string, mixed>  $row
     */
    private function assertNoImageKeys(array $row, string $label): void
    {
        $banned = ['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'];
        $hit = array_intersect($banned, array_keys($row));
        if ($hit !== []) {
            throw new \RuntimeException(
                "[{$label}] 画像系キーが検出されました（取得禁止）: ".implode(', ', $hit)
            );
        }
    }
}
