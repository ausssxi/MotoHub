<?php

declare(strict_types=1);

namespace App\Support\RentalBike\PriceFetchers;

/**
 * 事業者の料金ページから「車格別の参考価格（基準＝1日/24時間・保険別）」を集める共通インターフェース。
 * 会社ごとに料金ページの構造が違うので、実装は会社1つにつき1クラス。
 *
 * ★保険・補償・オプション・在庫・画像は扱わない。基準プランの基本料金だけを返す。
 */
interface PriceFetcher
{
    /** 事業者スラッグ（rental_bike_shops.company_slug と一致。yamaha / nirinsho など）。 */
    public function slug(): string;

    /** 取得元の料金ページURL（source_url に保存）。 */
    public function sourceUrl(): string;

    /**
     * 車格別の参考価格を返す。各要素のキー:
     *   vehicle_class（原付/125cc/250cc/400cc/大型）, plan（'daily'）, plan_label（原文「24時間」「1日」）,
     *   price_yen（int・基本料金・保険別）, is_from（bool・上位クラスがあり「¥○○〜」表記にする）,
     *   note（?string・車種により異なる/季節料金あり 等の補足。無ければ null）
     * ★1つも取れないときは空配列（＝コマンド側が「0件」として既存を上書きしない）。
     *
     * @return array<int, array{vehicle_class: string, plan: string, plan_label: string, price_yen: int, is_from: bool, note: ?string}>
     */
    public function fetch(): array;
}
