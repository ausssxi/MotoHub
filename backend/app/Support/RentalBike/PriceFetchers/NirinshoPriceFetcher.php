<?php

declare(strict_types=1);

namespace App\Support\RentalBike\PriceFetchers;

/**
 * 二輪処グループの料金（/price/）。基準＝「1日」列・基本料金・保険別。
 *
 * ★このページは表が多く（通常/夏季/冬季/保険/店舗別オプション）、構造に注意が必要:
 *   - 50cc・125cc: 基本料金表（[基本料金|1日|1週間|1ヶ月]、各ccに「特クラス/通常クラス」）から
 *     **通常クラスの「1日」**を取る（50cc→原付 / 125cc→125cc）。特クラスは取らない。
 *   - 250cc・400cc・大型: **通年(通常)の1日料金表が無く、夏季料金/冬季料金の表にしか出ない**。
 *     1日料金は夏季=冬季で同額（季節で変わるのは追加1日/1週間）。→ **夏季と冬季の「1日」が一致する
 *     ときだけ**「通年の1日」として取り込み、note に「季節料金あり」を付ける。一致しなければ取り込まない。
 * ★「夏季料金」そのもの（追加1日/1週間の季節差）は取り込まない。保険・補償・特クラスも取らない。
 * ★二輪処の「1日」は料金ページの定義「営業日を1日とし、営業時間内のレンタル」＝当日返却であり、
 *   ヤマハの「24時間」とは別物。plan_label は「1日（当日返却）」にして区別する（2026-09-28 確認）。
 */
final class NirinshoPriceFetcher extends AbstractPriceFetcher
{
    private const SOURCE_URL = 'https://www.bike-rental.jp/price/';

    /** 基本料金表(通常クラス)の cc 表記 → MotoHub車格。 */
    private const BASE_MAP = ['50cc' => self::CLASS_MOPED, '125cc' => self::CLASS_125];

    /** 季節料金表の cc 表記 → MotoHub車格。 */
    private const SEASON_MAP = ['250cc' => self::CLASS_250, '400cc' => self::CLASS_400, '大型' => self::CLASS_LARGE];

    public function slug(): string
    {
        return 'nirinsho';
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
     * 料金HTMLから車格別の「1日」基本料金を取り出す（テスト用に公開）。
     *
     * @return array<int, array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}>
     */
    public function extractPrices(string $html): array
    {
        $tables = array_map(fn (string $t): array => $this->tableRows($t), $this->tables($html));

        $prices = $this->baseNormalPrices($tables) + $this->seasonInvariantPrices($tables);

        // 車格の並び順で返す。
        $order = [self::CLASS_MOPED, self::CLASS_125, self::CLASS_250, self::CLASS_400, self::CLASS_LARGE];
        $out = [];
        foreach ($order as $class) {
            if (isset($prices[$class])) {
                $out[] = $prices[$class];
            }
        }

        return $out;
    }

    /**
     * 基本料金表から 50cc/125cc の「通常クラス・1日」を取る。
     *
     * @param  array<int, array<int, array<int, string>>>  $tables
     * @return array<string, array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}>
     */
    private function baseNormalPrices(array $tables): array
    {
        foreach ($tables as $rows) {
            $header = $rows[0] ?? [];
            if (! (in_array('基本料金', $header, true) && in_array('1日', $header, true) && in_array('1ヶ月', $header, true))) {
                continue;
            }

            $out = [];
            $current = null; // 直近の cc（rowspan で通常クラス行に cc が出ないため）
            foreach ($rows as $row) {
                $first = $row[0] ?? '';
                if (isset(self::BASE_MAP[$first])) {
                    $current = self::BASE_MAP[$first]; // cc 見出し行（この行自体は特クラス）
                } elseif ($first === '通常クラス' && $current !== null) {
                    $price = isset($row[1]) ? $this->yen($row[1]) : null; // 通常クラスの直後が「1日」
                    if ($price !== null) {
                        $out[$current] = $this->row($current, $price, false, null);
                    }
                    $current = null;
                }
            }

            if ($out !== []) {
                return $out;
            }
        }

        return [];
    }

    /**
     * 夏季料金表・冬季料金表の「1日」を車格ごとに集め、季節間で一致するものだけを通年1日として採用する。
     *
     * @param  array<int, array<int, array<int, string>>>  $tables
     * @return array<string, array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}>
     */
    private function seasonInvariantPrices(array $tables): array
    {
        /** @var array<string, list<int>> $seen */
        $seen = [];
        foreach ($tables as $rows) {
            $header = $rows[0] ?? [];
            $isSeason = in_array('夏季料金', $header, true) || in_array('冬季料金', $header, true);
            if (! $isSeason) {
                continue;
            }
            foreach ($rows as $row) {
                $first = $row[0] ?? '';
                if (isset(self::SEASON_MAP[$first]) && isset($row[1])) {
                    $price = $this->yen($row[1]); // 季節表の「1日」列
                    if ($price !== null) {
                        $seen[self::SEASON_MAP[$first]][] = $price;
                    }
                }
            }
        }

        $out = [];
        foreach ($seen as $class => $values) {
            $unique = array_values(array_unique($values));
            // 夏季・冬季の両方（2回以上）現れ、かつ全て同額のときだけ「通年の1日」として採用。
            // ★季節の但し書きは行ごとの note ではなく、店舗詳細ページ下部の一括注記で出す（note は付けない）。
            if (count($values) >= 2 && count($unique) === 1) {
                $out[$class] = $this->row($class, $unique[0], false, null);
            }
        }

        return $out;
    }

    /**
     * @return array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}
     */
    private function row(string $vehicleClass, int $priceYen, bool $isFrom, ?string $note): array
    {
        return [
            'vehicle_class' => $vehicleClass,
            'plan' => 'daily',
            'plan_label' => '1日（当日返却）',
            'price_yen' => $priceYen,
            'is_from' => $isFrom,
            'note' => $note,
        ];
    }
}
