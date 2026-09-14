<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レンタルバイク店舗（複数事業者を横断して集めた事実情報のみ）。
 * ★画像・紹介文・在庫は保持しない（ウェビックの掲載停止の経緯。今後も扱わない）。
 */
final class RentalBikeShop extends Model
{
    protected $fillable = [
        'company',
        'company_slug',
        'external_id',
        'name',
        'postal_code',
        'address',
        'prefecture',
        'city',
        'tel',
        'opening_hours',
        'latitude',
        'longitude',
        'geocode_failed_at',
        'official_url',
        'is_active',
        'fetched_at',
        'dedup_key',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geocode_failed_at' => 'datetime',
        'fetched_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * 一意キー: (company_slug, external_id) があればそれ、無ければ (company_slug, name, address)。
     * MySQL の索引長制限（191）と日本語のマルチバイトを避けるため、上記を sha1 に落として dedup_key に入れる。
     * updateOrCreate の照合はこのキー1本で行う（同じ店舗を2回取り込んでも重複しない）。
     */
    public static function makeDedupKey(string $companySlug, ?string $externalId, string $name, string $address): string
    {
        $composite = ($externalId !== null && $externalId !== '')
            ? $companySlug."\x1f".$externalId
            : $companySlug."\x1f".trim($name)."\x1f".trim($address);

        return sha1($composite);
    }
}
