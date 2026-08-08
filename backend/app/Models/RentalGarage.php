<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class RentalGarage extends Model
{
    use SoftDeletes;

    protected $table = 'rental_garages';

    protected $fillable = [
        'name',
        'operator',
        'garage_type',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'latitude',
        'longitude',
        'monthly_fee_min',
        'monthly_fee_max',
        'size_text',
        'is_24h',
        'has_power',
        'has_security',
        'has_shutter',
        'capacity',
        'phone',
        'website_url',
        'description',
        'source',
        'source_url',
        'submitted_by',
        'is_active',
        'is_verified',
        'geocode_status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_24h' => 'boolean',
        'has_power' => 'boolean',
        'has_security' => 'boolean',
        'has_shutter' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    private const GARAGE_TYPE_LABELS = [
        'indoor' => '屋内ガレージ',
        'container' => '屋外コンテナ',
        'open' => '青空月極',
        'other' => 'その他',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * garage_type の日本語ラベル。
     */
    public function getGarageTypeLabelAttribute(): string
    {
        return self::GARAGE_TYPE_LABELS[$this->garage_type] ?? 'その他';
    }

    /**
     * size_text の記号の表記ゆれを正規化する（唯一の正解の置き場所）。
     *
     * - 波ダッシュ U+301C「〜」と半角チルダ U+007E「~」を全角チルダ U+FF5E「～」に統一
     * - 帖 U+5E16 を畳 U+7573 に統一
     * - 前後の空白を trim
     * - null / 空文字は null
     *
     * ※ 数値書式や「2.8畳～10.1畳」のように単位が両側に付く形は変えない（機械的補正で情報を壊さない）。
     */
    public static function normalizeSizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\u{301C}", "\u{007E}"], "\u{FF5E}", $value);
        $value = str_replace("\u{5E16}", "\u{7573}", $value);
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * size_text ミューテタ。スクレイパー・ユーザー投稿・管理画面など全書き込み経路を
     * Eloquent 経由で正規化に集約する（normalizeSizeText を通す）。
     */
    public function setSizeTextAttribute(?string $value): void
    {
        $this->attributes['size_text'] = self::normalizeSizeText($value);
    }
}
