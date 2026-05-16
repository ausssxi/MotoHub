<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class TouringSpot extends Model
{
    protected $fillable = [
        'prefecture',
        'slug',
        'name',
        'lat',
        'lng',
        'description',
        'content',
        'image_url',
        'highlights',
        'recommended_season',
        'difficulty',
        'distance_km',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'highlights' => 'array',
        'distance_km' => 'integer',
    ];

    /**
     * Romaji slug → 日本語都道府県名マッピング (47都道府県)
     */
    public const PREFECTURE_SLUG_MAP = [
        'hokkaido' => '北海道',
        'aomori' => '青森県',
        'iwate' => '岩手県',
        'miyagi' => '宮城県',
        'akita' => '秋田県',
        'yamagata' => '山形県',
        'fukushima' => '福島県',
        'ibaraki' => '茨城県',
        'tochigi' => '栃木県',
        'gunma' => '群馬県',
        'saitama' => '埼玉県',
        'chiba' => '千葉県',
        'tokyo' => '東京都',
        'kanagawa' => '神奈川県',
        'niigata' => '新潟県',
        'toyama' => '富山県',
        'ishikawa' => '石川県',
        'fukui' => '福井県',
        'yamanashi' => '山梨県',
        'nagano' => '長野県',
        'gifu' => '岐阜県',
        'shizuoka' => '静岡県',
        'aichi' => '愛知県',
        'mie' => '三重県',
        'shiga' => '滋賀県',
        'kyoto' => '京都府',
        'osaka' => '大阪府',
        'hyogo' => '兵庫県',
        'nara' => '奈良県',
        'wakayama' => '和歌山県',
        'tottori' => '鳥取県',
        'shimane' => '島根県',
        'okayama' => '岡山県',
        'hiroshima' => '広島県',
        'yamaguchi' => '山口県',
        'tokushima' => '徳島県',
        'kagawa' => '香川県',
        'ehime' => '愛媛県',
        'kochi' => '高知県',
        'fukuoka' => '福岡県',
        'saga' => '佐賀県',
        'nagasaki' => '長崎県',
        'kumamoto' => '熊本県',
        'oita' => '大分県',
        'miyazaki' => '宮崎県',
        'kagoshima' => '鹿児島県',
        'okinawa' => '沖縄県',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function prefectureSlugRegex(): string
    {
        return implode('|', array_keys(self::PREFECTURE_SLUG_MAP));
    }

    public static function prefectureNameFromSlug(string $slug): ?string
    {
        return self::PREFECTURE_SLUG_MAP[$slug] ?? null;
    }

    public static function slugFromPrefectureName(string $name): ?string
    {
        return array_search($name, self::PREFECTURE_SLUG_MAP, true) ?: null;
    }

    public function scopeByPrefectureSlug($query, string $slug)
    {
        $name = self::prefectureNameFromSlug($slug);

        return $query->where('prefecture', $name);
    }
}
