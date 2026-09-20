<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use App\Models\RentalGarageType;
use App\Services\RentalGarage\KaseTypeParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 加瀬倉庫の物件ページから区画種別（type_code）を取得し rental_garage_types に保存する。
 *
 * ★ 取得対象は物件ページ（source_url）のみ。/api/ には一切アクセスしない（robots.txt Disallow）。
 * ★ 空き状況（isAvailable / availableCount）・画像は取得も保存もしない（パーサが type_code しか返さない）。
 * ★ ヘッドレスブラウザは使わない（生HTMLを Http で取得するだけ）。
 * ★ 1リクエストごとに 2 秒空け、並列リクエストは投げない。手動実行のみ（cron 登録しない）。
 *
 * 使い方:
 *   php artisan kase:fetch-types --dry-run            集計のみ（DB を一切書き換えない）
 *   php artisan kase:fetch-types --dry-run --limit=50 先頭50件だけ
 *   php artisan kase:fetch-types                      本実行（dry-run で内容を確認してから）
 *
 *   718ページ × 2秒 ≒ 25分。tail は使わず nohup でホームにログを出すこと:
 *     nohup php artisan kase:fetch-types > ~/kase_types.log 2>&1 &
 */
final class FetchKaseTypes extends Command
{
    protected $signature = 'kase:fetch-types {--dry-run : DB を書き換えず集計のみ表示} {--limit= : 先頭N件だけ処理}';

    protected $description = '加瀬倉庫の物件ページから区画種別(type_code)を取得して rental_garage_types へ保存する';

    /** 連絡先入りの User-Agent（ブラウザ偽装はしない）。 */
    private const USER_AGENT = 'MotoHub/1.0 (+https://motohub.jp; info@motohub.jp)';

    /** 1リクエストごとの待機秒。 */
    private const PAUSE_SECONDS = 2;

    private const OPERATOR = '加瀬倉庫';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $query = RentalGarage::query()
            ->where('operator', self::OPERATOR)
            ->whereNotNull('source_url')
            ->orderBy('id');

        $garages = $query->get();
        if ($limit !== null) {
            $garages = $garages->take($limit);
        }

        $allowed = array_keys((array) config('rental_garage.kase_types', []));
        if ($allowed === []) {
            $allowed = KaseTypeParser::KNOWN_CODES;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'加瀬倉庫 区画種別の取得を開始: 対象 '.$garages->count().' 件');

        $processed = 0;
        $fetchFailed = 0;
        $noId = 0;
        $parseEmpty = 0;
        $rowsWritten = 0;
        $typeTally = array_fill_keys($allowed, 0); // code => 物件数
        $withAny = 0;

        foreach ($garages as $garage) {
            $objectId = $this->objectIdFromUrl((string) $garage->source_url);
            if ($objectId === null) {
                $noId++;
                $this->warn("  ID抽出不可 (garage_id={$garage->id}): {$garage->source_url}");

                continue;
            }

            $this->pause();
            $html = $this->fetch((string) $garage->source_url);
            if ($html === null) {
                $fetchFailed++;
                $this->warn("  取得失敗 (garage_id={$garage->id}, object={$objectId})");

                continue;
            }

            $codes = KaseTypeParser::parse($html, $objectId, $allowed);
            $processed++;

            if ($codes === []) {
                $parseEmpty++;
            } else {
                $withAny++;
                foreach ($codes as $code) {
                    $typeTally[$code] = ($typeTally[$code] ?? 0) + 1;
                }
            }

            if (! $dryRun) {
                $rowsWritten += $this->sync($garage->id, $codes);
            }

            if ($processed % 100 === 0) {
                $this->line("  進捗: {$processed} 件処理");
            }
        }

        $this->newLine();
        $this->info('── 集計 ──');
        $this->line('対象物件            : '.$garages->count());
        $this->line('取得・パース成功    : '.$processed);
        $this->line('  うち種別あり      : '.$withAny);
        $this->line('  うち種別0（空）   : '.$parseEmpty);
        $this->line('取得失敗            : '.$fetchFailed);
        $this->line('URLからID抽出不可   : '.$noId);
        $this->newLine();
        $this->info('── 種別内訳（物件数）──');
        foreach ($typeTally as $code => $n) {
            $label = (string) config("rental_garage.kase_types.{$code}", $code);
            $this->line(sprintf('  %-9s %-16s : %d', $code, $label, $n));
        }

        $this->reportSlopeExcluded();

        $this->newLine();
        if ($dryRun) {
            $this->info('[DRY-RUN] DB は一切変更していません。内容を確認のうえ本実行してください。');
        } else {
            $this->info("本実行完了: rental_garage_types に {$rowsWritten} 行を新規追加（既存は updateOrCreate で重複なし）。");
        }

        return self::SUCCESS;
    }

    /**
     * garage の type_code 集合を DB に同期する。追加は create、外れたコードは削除。
     * (rental_garage_id, type_code) はユニークのため二重実行しても重複しない（冪等）。
     *
     * @param  array<int, string>  $codes
     * @return int 新規追加した行数
     */
    private function sync(int $garageId, array $codes): int
    {
        $existing = RentalGarageType::query()
            ->where('rental_garage_id', $garageId)
            ->pluck('type_code')
            ->all();

        $toAdd = array_diff($codes, $existing);
        $toDelete = array_diff($existing, $codes);

        foreach ($toAdd as $code) {
            RentalGarageType::query()->create([
                'rental_garage_id' => $garageId,
                'type_code' => $code,
            ]);
        }

        if ($toDelete !== []) {
            RentalGarageType::query()
                ->where('rental_garage_id', $garageId)
                ->whereIn('type_code', $toDelete)
                ->delete();
        }

        return count($toAdd);
    }

    /**
     * スロープ・レンタル対象外物件（config）が name で何件一致するかを報告する。
     * 表示ロジック（RentalGarage::matchesKaseSlopeExcluded）と同じ完全一致で数える。
     * ネットワークに依存しないため dry-run でも本実行でも常に出す。
     */
    private function reportSlopeExcluded(): void
    {
        $excluded = (array) config('rental_garage.kase_slope.excluded', []);
        if ($excluded === []) {
            return;
        }

        $this->newLine();
        $this->info('── スロープ対象外物件の name 照合（表示の出し分けに使用）──');
        $matchedTotal = 0;
        foreach ($excluded as $token) {
            $n = RentalGarage::query()
                ->where('operator', self::OPERATOR)
                ->where('name', $token)
                ->count();
            $matchedTotal += $n;
            $flag = $n === 0 ? ' ← 一致0' : '';
            $this->line(sprintf('  %-16s : %d 件%s', $token, $n, $flag));
        }
        $this->line('  合計一致: '.$matchedTotal.' 件');
        if ($matchedTotal === 0) {
            $this->warn('  ※ 8件すべて未一致。表記ゆれの可能性。config/rental_garage.php の excluded を見直すこと。');
        }
    }

    /**
     * source_url 末尾の物件ID（数字）を取り出す。取れなければ null。
     * 例: https://www.kase3535.com/tokyo/otaku/400438/ → 400438
     */
    private function objectIdFromUrl(string $url): ?string
    {
        if (preg_match('~/([0-9]+)/?(?:[?\#].*)?$~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 物件ページの生HTMLを取得。失敗（4xx/5xx/接続不可）は null。
     * 連絡先入り UA を必ず送る。ヘッドレスブラウザは使わない（/api/ を叩かないため）。
     */
    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->connectTimeout(10)
                ->timeout(30)
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /** 1リクエストごとに 2 秒空ける（テスト実行時は待たない）。 */
    private function pause(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        sleep(self::PAUSE_SECONDS);
    }
}
