<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoadsideStation;
use Illuminate\Console\Command;

/**
 * 道の駅 nickname（"A|B|A" のようなパイプ区切り別名リスト）を正規化する。
 * 既定は dry-run。実際の書き込みは --execute を明示したときのみ（ApplyRoadsideOfficialList と同方針）。
 *
 * 取り込み元データ由来の汚れを掃除する:
 *   - Wikipedia の URL スラッグ由来でスペースがアンダースコア(U+005F)になっている
 *   - 半角中黒(U+FF65)の混入
 *   - 完全一致の別名が重複している
 *
 * 各要素への処理順（要素ごと）:
 *   a. アンダースコア U+005F → 半角スペース U+0020
 *   b. 半角中黒 U+FF65 → 全角中黒 U+30FB
 *   c. 連続する半角スペースを 1 つに畳む
 *   d. 前後の空白を trim
 * その後、空要素を除去 → 完全一致の重複除去（初出を残し順序保持）→ '|' で連結。
 *
 * ※ 全角スペース U+3000 は変換しない。全角英数字も変換しない。
 * ※ nickname 以外のカラムは変更しない。
 */
final class NormalizeRoadsideNicknames extends Command
{
    protected $signature = 'roadside:normalize-nicknames
        {--execute : 実際に更新する（未指定は dry-run）}
        {--limit= : 処理する行数の上限}';

    protected $description = '道の駅 nickname（パイプ区切り別名）の正規化：アンダースコア・半角中黒・重複の除去（既定 dry-run）';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '' && ctype_digit((string) $limitOpt))
            ? (int) $limitOpt
            : null;

        if (! $execute) {
            $this->info('[DRY RUN] データは更新されません。');
        }

        $evaluated = 0;
        $changed = 0;
        $unchanged = 0;
        $skipped = 0;
        $samples = [];

        $query = RoadsideStation::query()->orderBy('id');

        $query->chunkById(200, function ($stations) use (
            $execute,
            $limit,
            &$evaluated,
            &$changed,
            &$unchanged,
            &$skipped,
            &$samples,
        ): bool {
            foreach ($stations as $station) {
                if ($limit !== null && $evaluated >= $limit) {
                    return false;
                }

                $original = $station->nickname;

                // null / 空文字はスキップ（評価行数にも含めない）。
                if (! is_string($original) || $original === '') {
                    $skipped++;

                    continue;
                }

                $evaluated++;

                $normalized = $this->normalizeNickname($original);

                if ($normalized === $original) {
                    $unchanged++;

                    continue;
                }

                $changed++;
                if (count($samples) < 30) {
                    $samples[] = [
                        'station_code' => (string) $station->station_code,
                        'name' => (string) $station->name,
                        'from' => $original,
                        'to' => $normalized,
                    ];
                }

                if ($execute) {
                    $station->update(['nickname' => $normalized]);
                }
            }

            // limit に到達していたら以降のチャンクを打ち切る。
            return ! ($limit !== null && $evaluated >= $limit);
        });

        if ($samples !== []) {
            $this->newLine();
            $this->line('変更対象（最大30件）:');
            foreach ($samples as $s) {
                $this->line("  {$s['station_code']} / {$s['name']} / 「{$s['from']}」→「{$s['to']}」");
            }
        }

        $this->newLine();
        $this->info('評価行数        : '.$evaluated);
        $this->info('変更対象件数     : '.$changed);
        $this->info('変更なし件数     : '.$unchanged);
        $this->info('スキップ（空）件数 : '.$skipped);

        if (! $execute) {
            $this->newLine();
            $this->warn('DRY RUN のため実際の更新は行っていません。');
        }

        return self::SUCCESS;
    }

    /**
     * パイプ区切りの nickname を正規化して返す。
     */
    private function normalizeNickname(string $nickname): string
    {
        $parts = explode('|', $nickname);

        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            // a. アンダースコア U+005F → 半角スペース U+0020
            $part = str_replace('_', ' ', $part);
            // b. 半角中黒 U+FF65 → 全角中黒 U+30FB
            $part = str_replace("\u{FF65}", "\u{30FB}", $part);
            // c. 連続する半角スペースを 1 つに畳む（全角スペース U+3000 は対象外）
            $part = preg_replace('/ +/', ' ', $part);
            // d. 前後の空白を trim
            $part = trim($part);

            // 空要素は除去
            if ($part === '') {
                continue;
            }

            // 完全一致の重複除去（初出を残し順序保持）
            if (isset($seen[$part])) {
                continue;
            }
            $seen[$part] = true;
            $out[] = $part;
        }

        return implode('|', $out);
    }
}
