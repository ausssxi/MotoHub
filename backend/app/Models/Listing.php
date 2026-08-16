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
     * 画像を一切表示しないサイトID（sites テーブル: 1=GooBike / 2=BDS / 3=Webike）。
     *
     * Webike(=3): 権利者・株式会社リバークレイン（ウェビック）より 2026-08-10 付で
     * 「許諾は致しかねますので、取得済の画像も含めた掲載の停止をお願いします」との回答を受領。
     * → 取得済のローカル画像(local_image_paths)も、先方CDN直リンク(image_urls)も表示に一切使わない。
     *   DB のデータ（image_urls 等）は将来のために残し、表示経路のアクセサでのみ画像なし扱いにする。
     *   ※ local_image_paths を消すと images アクセサが image_urls(先方CDN直リンク)へフォールバックし、
     *     閲覧のたびに先方サーバへ直リンク要求が飛ぶ“より悪い”状態になるため、判定は site_id で行う。
     *
     * @var array<int, int>
     */
    public const IMAGE_SUPPRESSED_SITE_IDS = [3];

    /**
     * この在庫が「画像掲載停止」対象サイト（Webike 等）のものか。
     * これが true のとき images / image_urls / local_image_paths は表示上すべて空になる。
     *
     * ★フェイルクローズド: site_id を select していない（＝判定不能）クエリでは、安全側に倒して
     *   「停止」とみなす（true）。こうしないと site_id 未ロード時に (int)null=0 で「停止でない」と
     *   誤判定し、削除済みのローカルパスがそのまま出力されてリンク切れ画像になる（本番で発生）。
     *   array_key_exists で「未ロード（キー無し）」と「値が null（ロード済みだが site_id が NULL）」を
     *   区別する。後者はロード済みなので判定可能＝通常判定（NULL は停止対象外）に回す。
     */
    public function imagesAreSuppressed(): bool
    {
        $attributes = $this->getAttributes();

        if (! array_key_exists('site_id', $attributes)) {
            return true; // 判定不能 → 安全側（画像を出さない）
        }

        return in_array((int) $attributes['site_id'], self::IMAGE_SUPPRESSED_SITE_IDS, true);
    }

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
        return $query->with(['shop', 'bikeModel.manufacturer', 'bikeModel.marketStats', 'bikeModel.nationalPriceStat', 'tags']);
    }

    public function toSearchableArray(): array
    {
        // 念のための保険（makeAllSearchableUsing があるので基本は不要ですが残しておきます）
        $this->loadMissing(['tags', 'bikeModel.marketStats', 'bikeModel.manufacturer', 'bikeModel.nationalPriceStat', 'shop']);

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'manufacturer_name' => $this->bikeModel?->manufacturer?->name ?? '',
            'bike_model_name' => $this->bikeModel?->name ?? '',

            'manufacturer_id' => (int) $this->manufacturer_id,
            'bike_model_id' => (int) $this->bike_model_id,
            'category_id' => (int) $this->category_id,

            // shopのデータが確実に取得されるため、ここに正しい都道府県が入ります
            'prefecture' => $this->shop?->prefecture ?? '',

            'total_price' => (int) $this->total_price,
            'mileage' => (int) $this->mileage,
            'model_year' => (int) $this->model_year,
            'displacement' => (int) $this->displacement,
            'is_new' => (int) $this->is_new,
            'has_repair_history' => (int) $this->has_repair_history,
            'is_sold_out' => (int) $this->is_sold_out,
            'bargain_score' => (float) $this->bargain_score,
            'view_count_today' => (int) $this->view_count_today,
            'favorite_count' => (int) $this->favorite_count,
            'created_at' => (int) $this->created_at->timestamp,
            'tag_slugs' => $this->tags->pluck('slug')->toArray(),
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
                // 掲載停止サイト（Webike 等）は、ローカル画像も先方CDN直リンクも使わず画像なし扱い。
                // ここで弾かないと local が空のとき image_urls（先方CDN）へフォールバックしてしまう。
                if ($this->imagesAreSuppressed()) {
                    return [];
                }
                if (! empty($attributes['local_image_paths'])) {
                    $localPaths = json_decode($attributes['local_image_paths'], true);
                    if (is_array($localPaths) && ! empty($localPaths)) {
                        return array_map(fn ($path) => listing_image_url($path), $localPaths);
                    }
                }
                if (! empty($attributes['image_urls'])) {
                    $remoteUrls = json_decode($attributes['image_urls'], true);

                    return is_array($remoteUrls) ? $remoteUrls : [];
                }

                return [];
            },
        );
    }

    /**
     * image_urls（先方CDN直リンク配列）。掲載停止サイトは空配列を返し、直リンク表示・直リクエストを止める。
     * ※ $casts の 'array' より get mutator が優先されるため、$value は生JSON文字列で渡る（自前で decode）。
     *   掲載停止でない場合は従来のキャスト（null→null / JSON→配列）と同じ結果を返す。
     * ※ 判定に site_id を使うため、image_urls を表示に使うクエリは site_id も select すること
     *   （partial select で site_id を落とすと停止判定ができない）。
     */
    protected function imageUrls(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if ($this->imagesAreSuppressed()) {
                    return [];
                }

                return $value === null ? null : json_decode($value, true);
            },
        );
    }

    /**
     * local_image_paths（取得済みローカル画像の相対パス配列）。掲載停止サイトは空配列を返す。
     * ※ 物理ファイルの掃除（PruneLocalListingImages）は表示ポリシーと無関係なので getRawOriginal で生値を読む。
     */
    protected function localImagePaths(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if ($this->imagesAreSuppressed()) {
                    return [];
                }

                return $value === null ? null : json_decode($value, true);
            },
        );
    }

    /**
     * お買い得スコア（Meilisearchのsortable/rankingRules `bargain_score` の正本）。
     * 旧: marketStats.avg_price 基準（外れ値で94%が出てソート上位に居座る）。
     * 新: 全国中央値ベース（RegionalBargainService::sortScore＝robust20・>50%は0・割高0）。
     * toSearchableArray が $this->bargain_score を index へ入れる経路がこれを使う。
     * ⚠️ 一括同期(scout:sync-flagged / makeAllSearchableUsing)では nationalPriceStat を
     *    eager-load すること（per-listing lazy load による N+1 防止）。
     */
    public function getBargainScoreAttribute(): float
    {
        return \App\Services\Bike\RegionalBargainService::sortScore(
            $this->bikeModel?->nationalPriceStat,
            $this->total_price !== null ? (int) $this->total_price : null
        );
    }

    // --- Relations ---
    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

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
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'is_notified' => false,
                    ]);
                }
            }
        });
    }

    // --- Query Scopes ---

    /**
     * 水増し対策: shop_id × bike_model_id の1日あたりカウントを最大5台に制限
     * is_sold_out=true、掲載3日以上、日付範囲フィルタを含む（成約判定 条件#1〜#3）。
     *
     * MySQL本番: 条件#1〜#3は日次バッチ listings:compute-capped-sold が事前計算し
     *   listings.is_capped_sold に保存済み。ここでは期間フィルタ＋フラグ参照のみ
     *   （重い ROW_NUMBER() ウィンドウ＝約14万行スキャンをリクエストパスから除去）。
     *   判定ロジックの正本は ComputeCappedSold コマンド。フラグは毎日04:00に更新。
     * sqlite(テスト): バッチ無し・小データのため従来のライブ・ウィンドウ計算を維持。
     */
    public function scopeCappedSold(Builder $query, Carbon $start, Carbon $end): Builder
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        // MySQL本番: 事前計算フラグ参照（日境界スナップは従来と同一挙動）。
        if ($query->getConnection()->getDriverName() === 'mysql') {
            return $query->where('listings.is_capped_sold', 1)
                ->whereBetween('listings.updated_at', [$from, $to]);
        }

        // sqlite等: INTERVAL 3 DAY の等価構文（テスト環境用・従来のライブ計算を維持）。
        // ORDER BY に id を加えタイブレーク（同一updated_atで決定的に）＝バッチ(ComputeCappedSold)と一致。
        return $query->whereRaw("listings.id IN (
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY shop_id, bike_model_id, DATE(updated_at)
                    ORDER BY updated_at, id
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
     * 一括sold_out除外: 事前計算フラグ listings.is_bulk_sold で除外（ranking:compute-bulk-exclusions が更新）。
     * cappedSold の後にチェーンして使う。
     * ※旧実装は Redis の除外IDリストを whereNotIn に展開していたが、件数増でSQLが肥大し破綻したため
     *   cappedSold(is_capped_sold) と同じフラグ列方式へ移行（件数無関係で一定サイズのSQL）。
     */
    public function scopeExcludeBulkSold(Builder $query): Builder
    {
        return $query->where('listings.is_bulk_sold', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('listings.is_sold_out', false);
    }

    public function scopeWithKeyword(Builder $query, ?string $keyword): Builder
    {
        if (! $keyword) {
            return $query;
        }
        $joins = collect($query->getQuery()->joins)->pluck('table');
        if (! $joins->contains('bike_models')) {
            $query->leftJoin('bike_models', 'listings.bike_model_id', '=', 'bike_models.id');
        }
        if (! $joins->contains('manufacturers')) {
            $query->leftJoin('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id');
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('listings.title', 'like', "%{$keyword}%")->orWhere('bike_models.name', 'like', "%{$keyword}%")->orWhere('manufacturers.name', 'like', "%{$keyword}%");
        });
    }

    public function scopeByPrefecture(Builder $query, ?string $prefecture): Builder
    {
        if (! $prefecture) {
            return $query;
        }
        if (! collect($query->getQuery()->joins)->pluck('table')->contains('shops')) {
            $query->join('shops', 'listings.shop_id', '=', 'shops.id');
        }

        return $query->where('shops.prefecture', 'like', "{$prefecture}%");
    }

    public function scopeByModel(Builder $query, ?int $manufacturerId, ?int $bikeModelId): Builder
    {
        if ($bikeModelId) {
            $query->where('listings.bike_model_id', $bikeModelId);
        } elseif ($manufacturerId) {
            $query->where('listings.manufacturer_id', $manufacturerId);
        }

        return $query;
    }

    public function scopeByCategory(Builder $query, ?int $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('listings.category_id', $categoryId);
    }

    public function scopeByCondition(Builder $query, $isNew): Builder
    {
        if ($isNew === null || $isNew === '') {
            return $query;
        }
        $value = ($isNew === '1' || $isNew === true) ? '新車' : '中古車';

        return $query->where('listings.condition', $value);
    }

    public function scopeByRepairHistory(Builder $query, $hasRepair): Builder
    {
        if ($hasRepair === null || $hasRepair === '') {
            return $query;
        }

        return $query->where('listings.has_repair_history', (bool) $hasRepair);
    }

    public function scopePriceBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder
    {
        if ($min > 0) {
            $query->where('listings.total_price', '>=', $min * 10000);
        }
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) {
            $query->where('listings.total_price', '<=', $max * 10000);
        }

        return $query;
    }

    public function scopeMileageBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder
    {
        if ($min > 0) {
            $query->where('listings.mileage', '>=', $min);
        }
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) {
            $query->where('listings.mileage', '<=', $max);
        }

        return $query;
    }

    public function scopeYearBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min) {
            $query->where('listings.model_year', '>=', $min);
        }
        if ($max) {
            $query->where('listings.model_year', '<=', $max);
        }

        return $query;
    }

    public function scopeDisplacementBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if (! $min && ! $max) {
            return $query;
        }
        if ($min) {
            $query->where('listings.displacement', '>=', $min);
        }
        if ($max) {
            $query->where('listings.displacement', '<=', $max);
        }

        return $query;
    }

    public function scopeWithTag(Builder $query, ?string $tagSlug): Builder
    {
        if (! $tagSlug) {
            return $query;
        }

        return $query->whereHas('tags', function (Builder $q) use ($tagSlug) {
            $q->where('tags.slug', $tagSlug);
        });
    }
}
