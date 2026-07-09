<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BikeNews extends Model
{
    protected $table = 'bike_news';

    /**
     * オリジナル記事（横断在庫データ由来の自社コンテンツ）の source 値。
     * RSS 取込記事は publisher 名（goo-net.com 等）が入る。全 news:generate-* が 'MotoHub' を使う。
     */
    public const SOURCE_ORIGINAL = 'MotoHub';

    protected $fillable = [
        'title',
        'url',
        'source',
        'content',
        'thumbnail_url',
        'published_at',
        'bike_model_id',
        'manufacturer_id',
        'comments_count',
        'picks_count',
        'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'comments_count' => 'integer',
        'picks_count' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class, 'news_id');
    }

    public function picks(): HasMany
    {
        return $this->hasMany(NewsPick::class, 'news_id');
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('published_at');
    }

    /**
     * オリジナル記事（source='MotoHub'）のみ。/news 本体で使用。
     * ⚠️ 車種・車両ページの「この車種のニュース」は RSS を含める仕様のため、このスコープを使わない。
     */
    public function scopeOriginal($query)
    {
        return $query->where('source', self::SOURCE_ORIGINAL);
    }
}
