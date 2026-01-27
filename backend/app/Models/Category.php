<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $guarded = ['id'];

    /**
     * 表示用のアイコンURLを取得するアクセサ
     * 使用法: $category->display_icon_url
     */
    protected function displayIconUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // 1. ローカル保存された画像がある場合
                if (!empty($attributes['local_icon_path'])) {
                    return asset('storage/' . ltrim($attributes['local_icon_path'], '/'));
                }
                
                // 2. 外部URLがある場合
                if (!empty($attributes['icon_url'])) {
                    return $attributes['icon_url'];
                }

                // 3. 画像がない場合（デフォルトアイコンなどを返しても良い）
                return null;
            },
        );
    }

    public function bikeModels(): HasMany
    {
        return $this->hasMany(BikeModel::class);
    }
}