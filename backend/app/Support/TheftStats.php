<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TouringSpot;

/**
 * バイク盗難統計（オートバイ盗・都道府県×年次）の参照（純関数・DB非依存）。
 *
 * ・データは database/data/theft_stats.json（一次情報=警察庁犯罪統計 第9表 を整形。docs/theft-data.md）。
 * ・検挙率・全国順位・推移は「表示時算出」＝キャッシュ非依存（維持費/ローンブロックと同型・bump不要）。
 * ・県名↔正規名の名寄せはここに集約（完全一致→前方一致フォールバック）。
 * ・数字は創作しない：データ未投入/欠損時は安全に null/空を返し、呼び出し側がブロックを隠す。
 */
final class TheftStats
{
    /** @var array<string, mixed>|null プロセス内キャッシュ */
    private static ?array $cache = null;

    /** 生データ（years/prefectures/meta）をロード。ファイル無し/壊れは空構造。 */
    private static function data(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path((string) config('theft.data_path', 'database/data/theft_stats.json'));
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($decoded)) {
            $decoded = [];
        }

        return self::$cache = [
            'meta' => $decoded['meta'] ?? [],
            'years' => array_values(array_map('intval', $decoded['years'] ?? [])),
            'prefectures' => is_array($decoded['prefectures'] ?? null) ? $decoded['prefectures'] : [],
        ];
    }

    /** 実データが投入済みか（未投入なら面①②は「準備中」/非表示にする）。 */
    public static function hasData(): bool
    {
        $d = self::data();

        return ! empty($d['years']) && ! empty($d['prefectures']);
    }

    /** @return array<int,int> 対象年（昇順） */
    public static function years(): array
    {
        $y = self::data()['years'];
        sort($y);

        return $y;
    }

    /** 最新（確定）年。 */
    public static function latestYear(): ?int
    {
        $y = self::years();

        return $y ? (int) end($y) : null;
    }

    /**
     * 県名（URLの都道府県）→ 正規フルネームに名寄せ。完全一致→前方一致（「東京」→「東京都」）。
     */
    public static function resolvePrefecture(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $canonical = array_values(TouringSpot::PREFECTURE_SLUG_MAP); // 47 フルネーム
        if (in_array($name, $canonical, true)) {
            return $name;
        }
        foreach ($canonical as $full) {
            if (str_starts_with($full, $name) || str_starts_with($name, $full)) {
                return $full;
            }
        }

        return null;
    }

    /**
     * 県別サマリー。データ無し/未知県/最新年欠損なら null（＝ブロック非表示）。
     *
     * @return array{prefecture:string, year:int, recognized:int, cleared:int, clearance_rate:?float, rank:int, total:int, series:array<int,array{year:int,recognized:int}>}|null
     */
    public static function forPrefecture(?string $name): ?array
    {
        if (! self::hasData()) {
            return null;
        }
        $pref = self::resolvePrefecture($name);
        $year = self::latestYear();
        if ($pref === null || $year === null) {
            return null;
        }

        $d = self::data();
        $row = $d['prefectures'][$pref][(string) $year] ?? null;
        if (! is_array($row) || ! isset($row['recognized'])) {
            return null; // 最新年の認知が無い県は安全に非表示
        }

        $recognized = (int) $row['recognized'];
        $cleared = (int) ($row['cleared'] ?? 0);

        // 推移（存在する年のみ・昇順）
        $series = [];
        foreach (self::years() as $y) {
            $r = $d['prefectures'][$pref][(string) $y]['recognized'] ?? null;
            if ($r !== null) {
                $series[] = ['year' => $y, 'recognized' => (int) $r];
            }
        }

        return [
            'prefecture' => $pref,
            'year' => $year,
            'recognized' => $recognized,
            'cleared' => $cleared,
            'clearance_rate' => self::clearanceRate($recognized, $cleared),
            'rank' => self::rankOf($pref, $year),
            'total' => count(self::rankingTable()),
            'series' => $series,
        ];
    }

    /**
     * 全国ランキング（最新年・認知件数降順）。順位は標準競争順位（1224方式）：
     * 同数は同順位、次順位は件数分スキップ（例：3位が2県なら次は5位）。同数の表示順は都道府県コード順で安定。
     *
     * @return array<int,array{rank:int, prefecture:string, recognized:int, cleared:int, clearance_rate:?float}>
     */
    public static function rankingTable(): array
    {
        return array_map(fn ($r) => [
            'rank' => $r['rank'],
            'prefecture' => $r['prefecture'],
            'recognized' => $r['recognized'],
            'cleared' => $r['cleared'],
            'clearance_rate' => self::clearanceRate($r['recognized'], $r['cleared']),
        ], self::rankedRows());
    }

    /**
     * rankingBase に標準競争順位（1224）を付与。同数（recognized 一致）は同順位、
     * 次の異なる値は「その要素の並び位置＋1」＝スキップ。
     *
     * @return array<int,array{prefecture:string, recognized:int, cleared:int, _ord:int, rank:int}>
     */
    private static function rankedRows(): array
    {
        $rows = self::rankingBase();
        $prevVal = null;
        $prevRank = 0;
        foreach ($rows as $i => $r) {
            $rank = ($prevVal !== null && $r['recognized'] === $prevVal) ? $prevRank : $i + 1;
            $rows[$i]['rank'] = $rank;
            $prevVal = $r['recognized'];
            $prevRank = $rank;
        }

        return $rows;
    }

    /**
     * 全国の年次推移（各年の47県合計認知件数・昇順）。ハブの折れ線用。
     *
     * @return array<int,array{year:int, recognized:int}>
     */
    public static function nationalSeries(): array
    {
        $d = self::data();
        $series = [];
        foreach (self::years() as $y) {
            $sum = 0;
            $has = false;
            foreach ($d['prefectures'] as $byYear) {
                $r = $byYear[(string) $y]['recognized'] ?? null;
                if ($r !== null) {
                    $sum += (int) $r;
                    $has = true;
                }
            }
            if ($has) {
                $series[] = ['year' => $y, 'recognized' => $sum];
            }
        }

        return $series;
    }

    /** @return array{label:string, url:string, data_year:int, checked_at:string} 出典メタ */
    public static function sourceMeta(): array
    {
        return [
            'label' => (string) config('theft.source_label', ''),
            'url' => (string) config('theft.source_url', ''),
            'data_year' => (int) config('theft.data_year', 0),
            'checked_at' => (string) config('theft.checked_at', ''),
        ];
    }

    // ---- 内部 ----

    private static function clearanceRate(int $recognized, int $cleared): ?float
    {
        if ($recognized <= 0) {
            return null;
        }

        return round($cleared / $recognized * 100, 1);
    }

    /** 最新年の {prefecture, recognized, cleared} を認知降順・県コード順で安定ソート。 */
    private static function rankingBase(): array
    {
        $d = self::data();
        $year = self::latestYear();
        if ($year === null) {
            return [];
        }
        $order = array_values(TouringSpot::PREFECTURE_SLUG_MAP); // 県コード順の正本
        $rows = [];
        foreach ($d['prefectures'] as $pref => $byYear) {
            $row = $byYear[(string) $year] ?? null;
            if (is_array($row) && isset($row['recognized'])) {
                $rows[] = [
                    'prefecture' => (string) $pref,
                    'recognized' => (int) $row['recognized'],
                    'cleared' => (int) ($row['cleared'] ?? 0),
                    '_ord' => array_search($pref, $order, true) ?: 999,
                ];
            }
        }
        usort($rows, fn ($a, $b) => $b['recognized'] <=> $a['recognized'] ?: ($a['_ord'] <=> $b['_ord']));

        return $rows;
    }

    private static function rankOf(string $pref, int $year): int
    {
        foreach (self::rankedRows() as $r) {
            if ($r['prefecture'] === $pref) {
                return $r['rank']; // 標準競争順位（1224）
            }
        }

        return 0;
    }
}
