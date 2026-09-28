<?php

declare(strict_types=1);

namespace App\Support\RentalBike\PriceFetchers;

/**
 * ヤマハ バイクレンタルの料金表（/jp/bike/info/fee）。基準＝「24時間」列・基本料金・保険別。
 *
 * 料金表(先頭table)の実構造（2026-09 確認）:
 *   [クラス | 基本料金 | 追加料金]
 *   [4時間 | 8時間 | 24時間 | 1時間ごと | 24時間ごと]   ← サブ見出し（列順）
 *   [～50cc | 3,000円 | 3,500円 | 4,500円 | ...]        ← データ行（先頭=クラス、以降=各プラン）
 *   [51cc～ | ...] [126cc～ | ...] [251cc～ | ...] [401cc～ | ...] [801cc～ | ...] [EXクラス | ...]
 *
 * 車格マッピング（排気量ベース）:
 *   ～50cc→原付 / 51cc～→125cc / 126cc～→250cc / 251cc～→400cc / 401cc～→大型
 *   ★大型は 401cc～ を代表値にし、上に 801cc～・EXクラスがあるため is_from=true（「¥○○〜」表記＋
 *     「車種により異なります」注記）。801cc～・EXクラスは取り込まない。
 * ★ヘルメット等オプション表・補償表は取らない。
 */
final class YamahaPriceFetcher extends AbstractPriceFetcher
{
    private const SOURCE_URL = 'https://bike-rental.yamaha-motor.co.jp/jp/bike/info/fee';

    /** 料金表のクラス表記 → [MotoHub車格, is_from]。801cc～・EXクラスは含めない（＝取り込まない）。 */
    private const CLASS_MAP = [
        '～50cc' => [self::CLASS_MOPED, false],
        '51cc～' => [self::CLASS_125, false],
        '126cc～' => [self::CLASS_250, false],
        '251cc～' => [self::CLASS_400, false],
        '401cc～' => [self::CLASS_LARGE, true], // 上位（801cc～/EX）があるので「〜」表記
    ];

    public function slug(): string
    {
        return 'yamaha';
    }

    public function sourceUrl(): string
    {
        return self::SOURCE_URL;
    }

    public function fetch(): array
    {
        $html = $this->get(self::SOURCE_URL);

        return $html === null ? [] : $this->extractPrices($html);
    }

    /**
     * 料金HTMLから車格別の「24時間」基本料金を取り出す（テスト用に公開）。
     *
     * @return array<int, array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}>
     */
    public function extractPrices(string $html): array
    {
        foreach ($this->tables($html) as $tableHtml) {
            $rows = $this->tableRows($tableHtml);
            $colIndex = $this->plan24hColumnIndex($rows);
            if ($colIndex === null) {
                continue; // 「24時間」列を持つ料金表でない
            }

            $out = [];
            foreach ($rows as $row) {
                $label = $row[0] ?? '';
                if (! isset(self::CLASS_MAP[$label])) {
                    continue; // クラス行でない（見出し・オプション等）
                }
                [$vehicleClass, $isFrom] = self::CLASS_MAP[$label];
                $price = isset($row[$colIndex]) ? $this->yen($row[$colIndex]) : null;
                if ($price === null) {
                    continue;
                }
                $out[$vehicleClass] = [
                    'vehicle_class' => $vehicleClass,
                    'plan' => 'daily',
                    'plan_label' => '24時間',
                    'price_yen' => $price,
                    'is_from' => $isFrom,
                    'note' => $isFrom ? '車種により異なります' : null,
                ];
            }

            if ($out !== []) {
                return array_values($out);
            }
        }

        return [];
    }

    /**
     * 料金表の各データ行のうち「24時間」料金が入っているセルのインデックスを返す。
     * サブ見出し行（4時間/8時間/24時間/…）の「24時間」位置 k に対し、データ行は先頭がクラス名なので 1+k。
     */
    private function plan24hColumnIndex(array $rows): ?int
    {
        foreach ($rows as $row) {
            $k = array_search('24時間', $row, true);
            if ($k !== false && in_array('4時間', $row, true)) {
                return 1 + (int) $k; // データ行は先頭にクラス名が入るぶんズレる
            }
        }

        return null;
    }
}
