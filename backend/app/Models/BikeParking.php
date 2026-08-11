<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BikeParking extends Model
{
    protected $table = 'bike_parkings';

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'tel',
        'latitude',
        'longitude',
        'prefecture',
        'city',
        'parking_type',
        'parking_form',
        'closed_days',
        'available_hours',
        'capacity',
        'price_per_hour',
        'price_per_day',
        'price_per_month',
        'price_detail',
        'vehicle_restriction',
        'notes',
        'management_company',
        'jmpsa_updated_at',
        'is_free',
        'is_covered',
        'is_locked',
        'has_security_camera',
        'available_24h',
        'description',
        'image_url',
        'source_url',
        'avg_rating',
        'reviews_count',
        'used_count',
        'is_verified',
        'is_active',
        'station_id',
        'geocode_failed_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'capacity' => 'integer',
        'price_per_hour' => 'integer',
        'price_per_day' => 'integer',
        'price_per_month' => 'integer',
        'jmpsa_updated_at' => 'date',
        'is_free' => 'boolean',
        'is_covered' => 'boolean',
        'is_locked' => 'boolean',
        'has_security_camera' => 'boolean',
        'available_24h' => 'boolean',
        'avg_rating' => 'float',
        'reviews_count' => 'integer',
        'used_count' => 'integer',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'geocode_failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ParkingReview::class, 'bike_parking_id');
    }

    /**
     * ユーザー投稿由来の「新規駐車場」か。新規は自作スポット＋自演レビュー対策で
     * レビューを承認待ちにする。既存（user_id=null＝スクレイパー/運営）は常に false。
     * 基準日数は config('shop.new_user_parking_days')。
     */
    public function isNewUserParking(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $days = (int) config('shop.new_user_parking_days', 14);
        if ($days <= 0 || $this->created_at === null) {
            return false;
        }

        return $this->created_at->greaterThanOrEqualTo(now()->subDays($days));
    }

    public function images(): HasMany
    {
        return $this->hasMany(BikeParkingImage::class)->orderBy('sort_order');
    }

    /**
     * 表示用画像URL（Shop::display_image_url と同等）。
     * 投稿画像(images)の先頭 → 外部 image_url → null。
     * ※ フィード等では images を eager load して N+1 を防ぐこと。
     */
    public function getDisplayImageUrlAttribute(): ?string
    {
        $first = $this->images->first();
        if ($first && ! empty($first->image_path)) {
            return asset('storage/'.ltrim($first->image_path, '/'));
        }
        if (! empty($this->image_url)) {
            return $this->image_url;
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeInBounds(Builder $query, float $swLat, float $swLng, float $neLat, float $neLng): Builder
    {
        return $query
            ->whereBetween('latitude', [$swLat, $neLat])
            ->whereBetween('longitude', [$swLng, $neLng]);
    }

    public function scopeByPrefecture(Builder $query, string $prefecture): Builder
    {
        return $query->where('prefecture', $prefecture);
    }

    /**
     * タイトル用のクリーンな駐車場名を返す
     * 元データの内部コード・記号を除去
     */
    public function getCleanNameAttribute(): string
    {
        $name = $this->name;
        // [英数字] 形式の内部コード除去（例: [176b2]）
        $name = preg_replace('/\[[a-zA-Z0-9]+\]/', '', $name);
        // 【数字】 形式の内部コード除去（例: 【1246】）
        $name = preg_replace('/【[0-9]+】/', '', $name);
        // ☆ を除去
        $name = str_replace('☆', '', $name);
        // 連続スペースを1つに
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    public function getParkingTypeLabel(): string
    {
        return match ($this->parking_type) {
            'bike_only' => 'バイク専用',
            'car_shared' => '四輪と共用',
            'bicycle_shared' => '自転車と共用',
            'other' => 'その他',
            default => 'その他',
        };
    }

    public function getPriceDisplay(): string
    {
        if ($this->is_free) {
            return '無料';
        }

        $parts = [];
        if ($this->price_per_hour) {
            $parts[] = number_format($this->price_per_hour).'円/時';
        }
        if ($this->price_per_day) {
            $parts[] = number_format($this->price_per_day).'円/日';
        }
        if ($this->price_per_month) {
            $parts[] = number_format($this->price_per_month).'円/月';
        }

        return $parts ? implode(' / ', $parts) : '料金不明';
    }

    /**
     * 運営会社（management_company）から地図ピンの運営分類キーを返す。
     *
     *  (A) 市区町村名で終わる（＝自治体名だけ） / NULL・空 → null（緑🅿️・バッジなし）
     *  (B) それ以外＝具体的な組織名 → config/parking.php brands に一致すればそのキー、
     *      一致しなければ 'other'（共通色バッジ＋会社名短縮ラベル）
     *
     * ★(A)判定は「正規化後の末尾が[市区町村]の1文字」で行う（実データ43,950件で検証済）。
     *   このため「岡山市北区」のように"区で終わる"文字列も(A)に落ちる既知の性質があるが、
     *   実データに該当は無い（本物は「岡山市北区役所維持管理課…」で末尾が[市区町村]でないため(B)）。
     */
    public static function parkingBrand(?string $mgmt): ?string
    {
        if ($mgmt === null || trim($mgmt) === '') {
            return null; // 運営会社なし → 緑
        }

        // (A) 末尾の全半角スペースだけ落として「市区町村で終わるか」を判定（検証tinkerと同一ロジック）。
        $trimmed = preg_replace('/[\s\x{3000}]+$/u', '', trim($mgmt)) ?? '';
        if (preg_match('/[市区町村]$/u', $trimmed)) {
            return null;
        }

        // (B) 組織名あり → 固有ブランド照合（両辺を正規化して部分一致）。
        $normalized = \App\Support\ShopNameNormalizer::normalize($mgmt);
        foreach ((array) config('parking.brands', []) as $key => $def) {
            foreach (($def['patterns'] ?? []) as $pattern) {
                $needle = \App\Support\ShopNameNormalizer::normalize((string) $pattern);
                if ($needle !== '' && str_contains($normalized, $needle)) {
                    return $key;
                }
            }
        }

        return 'other'; // 具体組織だが固有ブランド外 → 共通色バッジ
    }

    /**
     * 地図ピン/詳細に出す運営表示名。固有ブランドは config の表示名、
     * 'other' は会社名を短縮（長い役所名などをピンに載せられる長さに）。緑（null）は表示名なし。
     */
    public static function parkingOperatorLabel(?string $mgmt, ?string $brand): ?string
    {
        if ($brand === null) {
            return null;
        }
        if ($brand !== 'other') {
            return config("parking.brands.$brand.name", $brand);
        }

        $name = trim((string) $mgmt);

        return mb_strlen($name) > 10 ? mb_substr($name, 0, 10).'…' : $name;
    }
}
