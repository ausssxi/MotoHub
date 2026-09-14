<?php

declare(strict_types=1);

namespace App\Support\RentalBike;

/**
 * 各レンタルバイク事業者のサイトから「店舗の事実情報のみ」を集めるパーサの共通インターフェース。
 * 会社ごとにHTML構造が全く違うので、実装は会社1つにつき1クラス（会社追加＝Fetcher追加）。
 *
 * ★ fetch() が返す配列に画像に関するキーを持たせないこと（写真・ロゴ・紹介文は扱わない）。
 */
interface ShopFetcher
{
    /** 事業者スラッグ（bikecenter / motobase など）。 */
    public function slug(): string;

    /** 事業者の表示名。 */
    public function company(): string;

    /** 事業者の公式サイトURL。 */
    public function officialUrl(): string;

    /**
     * 店舗の配列を返す。各要素のキー:
     *   name, postal_code, address, prefecture, city, tel, opening_hours, official_url, external_id
     * ★画像キーは含めない。
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(): array;
}
