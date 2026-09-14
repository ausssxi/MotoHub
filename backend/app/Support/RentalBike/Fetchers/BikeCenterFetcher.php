<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * レンタルバイクセンター（スーパーバイクセンター系）。
 * 1ページに全店舗が都道府県別に並ぶ（ページ送り無し）。★FAXは取らない・画像は扱わない。
 */
final class BikeCenterFetcher extends AbstractFetcher
{
    private const URL = 'https://www.bikecenter-rental.jp/';

    public function slug(): string
    {
        return 'bikecenter';
    }

    public function company(): string
    {
        return 'レンタルバイクセンター';
    }

    public function officialUrl(): string
    {
        return self::URL;
    }

    public function fetch(): array
    {
        $body = $this->get(self::URL);

        return $body === null ? [] : $this->parse($body);
    }

    /**
     * 一覧ページHTMLから店舗を抽出（テスト用に公開）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $html): array
    {
        return $this->parseNamePostalTel($html);
    }
}
