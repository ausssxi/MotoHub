<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 車種マスタモデル
 */
final class BikeModel extends Model
{
    /**
     * 複数代入可能な属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'local_image_path',
        'displacement',
        'manufacturer_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'displacement' => 'integer',
        'local_image_path' => 'array',
    ];

    /**
     * 画像のフルURLを $bike->image_url で取得できるようにする
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 配列でない、または空の場合は null を返す
                if (!is_array($this->local_image_path) || empty($this->local_image_path)) {
                    return null;
                }

                // 先頭の / を削ってから storage/ と結合する（二重スラッシュ防止）
                $path = ltrim($this->local_image_path[0], '/');
                
                return asset('storage/' . $path);
            },
        );
    }

    /**
     * 所属するメーカーを取得
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * この車種に関連する出品情報を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * 各サイト別の識別番号を取得
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(BikeModelIdentifier::class);
    }
}