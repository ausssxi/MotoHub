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
}
