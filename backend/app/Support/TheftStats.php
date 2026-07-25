<?php

declare(strict_types=1);

namespace App\Support;

/**
 * バイク盗難統計（オートバイ盗・全国のみ）の参照（純関数・DB非依存）。
 *
 * ★都道府県別は警察庁/e-Statに機械可読で綺麗な「オートバイ盗×都道府県別×認知件数」が存在しないため断念。
 *   本クラスは「全国の年次トレンド」だけを扱う（第2表 窃盗 手口別のオートバイ盗＝全国値）。
 * ・データは database/data/theft_stats.json の national（年→{recognized, cleared}）。docs/theft-data.md。
 * ・年次配列・最新年サマリ・検挙率・前年比は「表示時算出」＝キャッシュ非依存。
 * ・数字は創作しない：未投入/欠損年は安全に除外し、データ無しなら hasData()=false でハブが「準備中」表示。
 */
final class TheftStats
{
    /** @var array<string, mixed>|null プロセス内キャッシュ */
    private static ?array $cache = null;

    /** national（年→{recognized,cleared}）をロード。ファイル無し/壊れは空。 */
    private static function national(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = base_path((string) config('theft.data_path', 'database/data/theft_stats.json'));
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $national = is_array($decoded['national'] ?? null) ? $decoded['national'] : [];

        return self::$cache = $national;
    }

    /**
     * 年次推移（認知/検挙）。recognized が入っている年のみ・年昇順。
     *
     * @return array<int,array{year:int, recognized:int, cleared:int}>
     */
    public static function series(): array
    {
        $rows = [];
        foreach (self::national() as $year => $v) {
            // recognized>0 の年のみ採用。0/欠損は「未投入」として除外（偽の0件トレンドを描かない）。
            if (is_array($v) && is_numeric($v['recognized'] ?? null) && (int) $v['recognized'] > 0) {
                $rows[] = [
                    'year' => (int) $year,
                    'recognized' => (int) $v['recognized'],
                    'cleared' => (int) ($v['cleared'] ?? 0),
                ];
            }
        }
        usort($rows, fn ($a, $b) => $a['year'] <=> $b['year']);

        return $rows;
    }

    /** 実データが1年でも入っているか（未投入なら /theft は「準備中」表示）。 */
    public static function hasData(): bool
    {
        return self::series() !== [];
    }

    /**
     * 最新年のサマリ（認知・検挙・検挙率・前年比）。データ無しなら null。
     *
     * @return array{year:int, recognized:int, cleared:int, clearance_rate:?float, yoy_pct:?float}|null
     */
    public static function latest(): ?array
    {
        $series = self::series();
        if ($series === []) {
            return null;
        }

        $last = end($series);
        $prev = count($series) >= 2 ? $series[count($series) - 2] : null;

        $yoy = null;
        if ($prev !== null && $prev['recognized'] > 0) {
            $yoy = round(($last['recognized'] - $prev['recognized']) / $prev['recognized'] * 100, 1);
        }

        return [
            'year' => $last['year'],
            'recognized' => $last['recognized'],
            'cleared' => $last['cleared'],
            'clearance_rate' => $last['recognized'] > 0 ? round($last['cleared'] / $last['recognized'] * 100, 1) : null,
            'yoy_pct' => $yoy,
        ];
    }

    /** @return array{label:string, url:string, checked_at:string} 出典メタ（config が正本・表示に必須） */
    public static function sourceMeta(): array
    {
        return [
            'label' => (string) config('theft.source_label', ''),
            'url' => (string) config('theft.source_url', ''),
            'checked_at' => (string) config('theft.checked_at', ''),
        ];
    }
}
