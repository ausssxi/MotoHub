<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 整備・カスタム記録の統合モデル（1テーブル maintenance_logs ＋ type）。
 * 給油は別テーブル fuel_logs のまま分離。将来 type=expense を追加予定。
 *
 * 公開ルール（visibilityフック・今回サーフェス無し）:
 *   記録が公開されるのは将来 bike.is_public && record.is_public の AND のときのみ。
 *   （カバー公開と同じ防御的既定。is_public は default false。）
 */
class MaintenanceLog extends Model
{
    use SoftDeletes;

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'my_bike_id',
        'type',
        'maintained_at',  // = 実施日(recorded_at)
        'odometer',
        'title',          // maintenance: 整備内容（リマインダーの項目キー）/ custom: part_name を流用
        'cost',
        'vendor',
        'payment_method',
        'note',
        'is_public',
        // custom 専用
        'part_name',
        'brand',
        'category',
        'is_installed',
        // custom 専用・商品連携（2a）
        'product_mall',
        'product_id',
        'product_name',
        'product_image_url',
        'product_price',
        'product_url',
        'product_price_fetched_at',
    ];

    protected $casts = [
        'maintained_at' => 'date',
        'odometer' => 'integer',
        'cost' => 'integer',
        'is_public' => 'boolean',
        'is_installed' => 'boolean',
        'product_price' => 'integer',
        'product_price_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => self::TYPE_MAINTENANCE,
    ];

    protected static function booted(): void
    {
        // 記録削除時に添付写真を「モデル経由」で削除し実ファイルも消す。
        // （FKのDBカスケードは MyBikeImage::deleting を発火させないため明示的にループ）
        static::deleting(function (MaintenanceLog $record): void {
            $record->images()->get()->each->delete();
        });
    }

    // 添付写真（記録の前後/ビルド写真・複数可・owner-only表示）。
    public function images(): HasMany
    {
        return $this->hasMany(MyBikeImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeMaintenance(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MAINTENANCE);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CUSTOM);
    }

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }
}
