<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tag;
use Laravel\Scout\Searchable;

class Listing extends Model
{
    use Searchable;

    protected $guarded = ['id'];

    protected $casts = [
        'image_urls' => 'array',
        'local_image_paths' => 'array',
        'has_repair_history' => 'boolean',
        'is_sold_out' => 'boolean',
        'needs_reindex' => 'boolean',
    ];

    /**
     * conditionカラム('新車'/'中古車')からis_newを算出するアクセサ
     */
    protected function isNew(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->condition === '新車',
        );
    }

    /**
     * ★追加の核心部分：インポート時のN+1問題を解決し、確実にリレーションを含める
     * このメソッドを書くことで、php artisan scout:import を実行した際に、
     * 最初から必ず shop などのデータを連結して（忘れずに）取得してきてくれます！
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['shop', 'bikeModel.manufacturer', 'bikeModel.marketStats', 'tags']);
    }

    public function toSearchableArray(): array
    {
        // 念のための保険（makeAllSearchableUsing があるので基本は不要ですが残しておきます）
        $this->loadMissing(['tags', 'bikeModel.marketStats', 'bikeModel.manufacturer', 'shop']);

        return [
            'id'                => (int) $this->id,
            'title'             => $this->title,
            'manufacturer_name' => $this->bikeModel?->manufacturer?->name ?? '',
            'bike_model_name'   => $this->bikeModel?->name ?? '',
            
            'manufacturer_id'   => (int) $this->manufacturer_id,
            'bike_model_id'     => (int) $this->bike_model_id,
            'category_id'       => (int) $this->category_id,
            
            // shopのデータが確実に取得されるため、ここに正しい都道府県が入ります
            'prefecture'        => $this->shop?->prefecture ?? '',
            
            'total_price'       => (int) $this->total_price,
            'mileage'           => (int) $this->mileage,
            'model_year'        => (int) $this->model_year,
            'displacement'      => (int) $this->displacement,
            'is_new'            => (int) $this->is_new,
            'has_repair_history'=> (int) $this->has_repair_history,
            'is_sold_out'       => (int) $this->is_sold_out,
            'bargain_score'     => (float) $this->bargain_score,
            'view_count_today'  => (int) $this->view_count_today,
            'favorite_count'    => (int) $this->favorite_count,
            'created_at'        => (int) $this->created_at->timestamp,
            'tag_slugs'         => $this->tags->pluck('slug')->toArray(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return true;
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($attributes['local_image_paths'])) {
                    $localPaths = json_decode($attributes['local_image_paths'], true);
                    if (is_array($localPaths) && !empty($localPaths)) {
                        return array_map(fn($path) => asset('storage/' . ltrim($path, '/')), $localPaths);
                    }
                }
                if (!empty($attributes['image_urls'])) {
                    $remoteUrls = json_decode($attributes['image_urls'], true);
                    return is_array($remoteUrls) ? $remoteUrls : [];
                }
                return [];
            },
        );
    }

    public function getBargainScoreAttribute(): float
    {
        $stats = $this->bikeModel?->marketStats;
        if (!$stats || $stats->avg_price <= 0 || !$this->total_price) {
            return 0;
        }
        $score = (($stats->avg_price - $this->total_price) / $stats->avg_price) * 100;
        return round(max(0, $score), 1);
    }

    // --- Relations ---
    public function bikeModel(): BelongsTo { return $this->belongsTo(BikeModel::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function tags(): BelongsToMany { return $this->belongsToMany(Tag::class); }
    public function priceHistories(): HasMany { return $this->hasMany(PriceHistory::class); }

    /**
     * ★追加: この車両をお気に入りしているユーザー（多対多）
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'listing_id', 'user_id')->withTimestamps();
    }

    /**
     * ★追加: モデルのイベント（更新時の価格チェックと値下げ履歴の保存）
     */
    protected static function booted()
    {
        static::updated(function ($listing) {
            // total_price が変更されたかチェック
            if ($listing->wasChanged('total_price')) {
                $oldPrice = $listing->getOriginal('total_price');
                $newPrice = $listing->total_price;

                // 以前の価格が存在し、新価格が安くなっている場合に履歴を作成
                if ($oldPrice > 0 && $newPrice > 0 && $newPrice < $oldPrice) {
                    \App\Models\PriceHistory::create([
                        'listing_id' => $listing->id,
                        'old_price'  => $oldPrice,
                        'new_price'  => $newPrice,
                        'is_notified'=> false,
                    ]);
                }
            }
        });
    }

    // --- Query Scopes ---

    /**
     * 水増し対策: shop_id × bike_model_id の1日あたりカウントを最大5台に制限
     * is_sold_out=true、掲載3日以上、日付範囲フィルタを含む
     */
    public function scopeCappedSold(Builder $query, Carbon $start, Carbon $end): Builder
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        // 「掲載3日以上」の日付演算はドライバ依存のため分岐（migrationと同じパターン）。
        // mysql側のSQL文字列は従来のまま一切変更しない（本番影響ゼロの保証）。
        if ($query->getConnection()->getDriverName() === 'mysql') {
            return $query->whereRaw('listings.id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY shop_id, bike_model_id, DATE(updated_at)
                    ORDER BY updated_at
                ) as rn
                FROM listings
                WHERE is_sold_out = 1
                AND created_at <= updated_at - INTERVAL 3 DAY
                AND updated_at BETWEEN ? AND ?
            ) as capped
            WHERE rn <= 5
        )', [$from, $to]);
        }

        // sqlite等: INTERVAL 3 DAY の等価構文（テスト環境用）
        return $query->whereRaw("listings.id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY shop_id, bike_model_id, DATE(updated_at)
                    ORDER BY updated_at
                ) as rn
                FROM listings
                WHERE is_sold_out = 1
                AND created_at <= datetime(updated_at, '-3 days')
                AND updated_at BETWEEN ? AND ?
            ) as capped
            WHERE rn <= 5
        )", [$from, $to]);
    }

    /**
     * 一括sold_out除外: 事前計算済みIDリスト（Redis）で除外
     * cappedSold の後にチェーンして使う
     */
    public function scopeExcludeBulkSold(Builder $query): Builder
    {
        $ids = \Illuminate\Support\Facades\Redis::connection()
            ->smembers(\App\Console\Commands\ComputeBulkExclusions::REDIS_KEY);

        if (empty($ids)) {
            return $query;
        }

        $ids = array_map('intval', $ids);

        return $query->whereNotIn('listings.id', $ids);
    }

    public function scopeActive(Builder $query): Builder { return $query->where('listings.is_sold_out', false); }
    public function scopeWithKeyword(Builder $query, ?string $keyword): Builder {
        if (!$keyword) return $query;
        $joins = collect($query->getQuery()->joins)->pluck('table');
        if (!$joins->contains('bike_models')) $query->leftJoin('bike_models', 'listings.bike_model_id', '=', 'bike_models.id');
        if (!$joins->contains('manufacturers')) $query->leftJoin('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id');
        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('listings.title', 'like', "%{$keyword}%")->orWhere('bike_models.name', 'like', "%{$keyword}%")->orWhere('manufacturers.name', 'like', "%{$keyword}%");
        });
    }
    public function scopeByPrefecture(Builder $query, ?string $prefecture): Builder {
        if (!$prefecture) return $query;
        if (!collect($query->getQuery()->joins)->pluck('table')->contains('shops')) $query->join('shops', 'listings.shop_id', '=', 'shops.id');
        return $query->where('shops.prefecture', 'like', "{$prefecture}%");
    }
    public function scopeByModel(Builder $query, ?int $manufacturerId, ?int $bikeModelId): Builder {
        if ($bikeModelId) $query->where('listings.bike_model_id', $bikeModelId);
        elseif ($manufacturerId) $query->where('listings.manufacturer_id', $manufacturerId);
        return $query;
    }
    public function scopeByCategory(Builder $query, ?int $categoryId): Builder {
        if (!$categoryId) return $query;
        return $query->where('listings.category_id', $categoryId);
    }
    public function scopeByCondition(Builder $query, $isNew): Builder {
        if ($isNew === null || $isNew === '') return $query;
        $value = ($isNew === '1' || $isNew === true) ? '新車' : '中古車';
        return $query->where('listings.condition', $value);
    }
    public function scopeByRepairHistory(Builder $query, $hasRepair): Builder {
        if ($hasRepair === null || $hasRepair === '') return $query;
        return $query->where('listings.has_repair_history', (bool)$hasRepair);
    }
    public function scopePriceBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder {
        if ($min > 0) $query->where('listings.total_price', '>=', $min * 10000);
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) $query->where('listings.total_price', '<=', $max * 10000);
        return $query;
    }
    public function scopeMileageBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder {
        if ($min > 0) $query->where('listings.mileage', '>=', $min);
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) $query->where('listings.mileage', '<=', $max);
        return $query;
    }
    public function scopeYearBetween(Builder $query, ?int $min, ?int $max): Builder {
        if ($min) $query->where('listings.model_year', '>=', $min);
        if ($max) $query->where('listings.model_year', '<=', $max);
        return $query;
    }
    public function scopeDisplacementBetween(Builder $query, ?int $min, ?int $max): Builder {
        if (!$min && !$max) return $query;
        if ($min) $query->where('listings.displacement', '>=', $min);
        if ($max) $query->where('listings.displacement', '<=', $max);
        return $query;
    }
    public function scopeWithTag(Builder $query, ?string $tagSlug): Builder {
        if (!$tagSlug) return $query;
        return $query->whereHas('tags', function (Builder $q) use ($tagSlug) {
            $q->where('tags.slug', $tagSlug);
        });
    }
}