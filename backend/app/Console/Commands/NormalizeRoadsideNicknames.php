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
 *   - 全角／半角・ヶ／ケ の表記ゆれだけが違う「ほぼ同一」の別名が重複している
 *     （例「よってけ！島牧」と「よってけ!島牧」）
 *
 * 各要素への処理順（要素ごと）:
 *   a. アンダースコア U+005F → 半角スペース U+0020
 *   b. 半角中黒 U+FF65 → 全角中黒 U+30FB
 *   c. 連続する半角スペースを 1 つに畳む
 *   d. 前後の空白を trim
 * その後、空要素を除去 → 完全一致の重複除去（初出を残し順序保持）
 *   → 表記ゆれ重複の除去 → '|' で連結。
 *
 * 表記ゆれ重複の判定キー（この3点を施した文字列が一致する別名同士を重複とみなす）:
 *   - 全角英数記号(U+FF01–FF5E)を半角へ（！→! ｉ→i Ｗ→W （）→() など）
 *   - 「ヶ」U+30F6 → 「ケ」U+30B1
 *   - 空白（半角・全角）を除去
 * 重複グループから残す1つは、その道の駅の name（「道の駅」を除いた部分）に含まれる
 * 表記があればそれ、無ければリスト内で最初に現れたもの。
 * ※ 別名の文字列そのものは書き換えない。重複を1つに落とすだけ。
 *
 * ※ 全角スペース U+3000 は変換しない。全角英数字も変換しない（＝表示文字列は不変）。
 *   判定キーだけが全角半角/ヶケ/空白を吸収する。
 * ※ nickname 以外のカラムは変更しない。
 */
final class NormalizeRoadsideNicknames extends Command
{
    protected $signature = 'roadside:normalize-nicknames
        {--execute : 実際に更新する（未指定は dry-run）}
        {--limit= : 処理する行数の上限}';

    protected $description = '道の駅 nickname（パイプ区切り別名）の正規化：アンダースコア・半角中黒・完全一致重複・全角半角/ヶケの表記ゆれ重複の除去（既定 dry-run）';

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

                $normalized = $this->normalizeNickname($original, (string) $station->name);

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
     * $name はその道の駅の名称（表記ゆれ重複でどれを残すか決めるのに使う）。
     */
    private function normalizeNickname(string $nickname, string $name): string
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

        // 表記ゆれ（全角半角・ヶ／ケ・空白差）だけが違う重複を1つにまとめる。
        return implode('|', $this->dedupeByKey($out, $name));
    }

    /**
     * 判定キーが一致する別名同士を重複とみなし、各グループから1つだけ残す。
     * 文字列自体は書き換えず、初出位置に残すもの（keeper）を置いて順序を保つ。
     *
     * @param  array<int, string>  $parts  完全一致重複を除いた別名リスト（順序保持）
     * @return array<int, string>
     */
    private function dedupeByKey(array $parts, string $name): array
    {
        // キーごとにメンバーを集める（リスト順を保持）。
        $groups = [];
        foreach ($parts as $part) {
            $groups[$this->dedupeKey($part)][] = $part;
        }

        $nameCore = $this->nameCore($name);

        // 初出位置に keeper を1つだけ置く（他は落とす）。
        $result = [];
        $done = [];
        foreach ($parts as $part) {
            $key = $this->dedupeKey($part);
            if (isset($done[$key])) {
                continue;
            }
            $done[$key] = true;
            $result[] = $this->chooseKeeper($groups[$key], $nameCore);
        }

        return $result;
    }

    /**
     * 重複グループから残す1つを選ぶ。
     *   - name（「道の駅」除去後）に含まれる表記があればそれ（リスト順で最初に一致したもの）
     *   - 無ければリストの最初のもの
     *
     * @param  array<int, string>  $members
     */
    private function chooseKeeper(array $members, string $nameCore): string
    {
        if ($nameCore !== '') {
            foreach ($members as $member) {
                if (mb_strpos($nameCore, $member) !== false) {
                    return $member;
                }
            }
        }

        return $members[0];
    }

    /**
     * 重複判定用のキーを作る。
     *   - 全角英数記号(U+FF01–FF5E) を半角へ（！→! ｉ→i Ｗ→W （）→() など）
     *   - 「ヶ」U+30F6 → 「ケ」U+30B1
     *   - 空白（半角・全角 U+3000）を除去
     * ※ カタカナの「ケ」U+30B1 はそのまま（ヶ→ケ の一方向のみ変換）。
     */
    private function dedupeKey(string $s): string
    {
        // 全角英数記号 U+FF01–FF5E → 半角 U+0021–007E（差は 0xFEE0 固定）。
        $s = preg_replace_callback(
            '/[\x{FF01}-\x{FF5E}]/u',
            static fn (array $m): string => mb_chr(mb_ord($m[0]) - 0xFEE0),
            $s
        ) ?? $s;

        // 「ヶ」→「ケ」
        $s = str_replace("\u{30F6}", "\u{30B1}", $s);

        // 空白（半角・全角）を除去
        $s = preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s;

        return $s;
    }

    /**
     * name から「道の駅」を除いた判定用の部分文字列を返す。
     */
    private function nameCore(string $name): string
    {
        return trim(str_replace('道の駅', '', $name));
    }
}
