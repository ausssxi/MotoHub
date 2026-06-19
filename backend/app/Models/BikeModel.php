<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 車種マスタモデル
 */
final class BikeModel extends Model
{
    /**
     * 車種詳細ページ(model_detail)キャッシュのバージョン。
     * $stats等のキャッシュ構造を変えた際にバンプすると、旧キャッシュを破棄せず自然に無効化できる。
     * （BikeController::modelDetailBySlug 等と ListingObserver::purgeModelDetailCache が同じキーを共有）
     *
     * ⚠️ このバージョンを bump したら、デプロイ後に必ず1回 `php artisan cache:warm-models --all`
     *    を実行して全車種を新キーで再温めすること（バンプ直後は全ページがコールド＝TTFB悪化、
     *    日次ウォーマーは07:30まで走らない）。cache:clear は使わない（上書きウォームのみ）。
     */
    public const MODEL_DETAIL_CACHE_VERSION = 'v4';

    /**
     * model_detail ページのキャッシュキーを生成する（生成側とパージ側で必ず一致させるための単一の正本）。
     * slugが無い車種はID、IDベースのフォールバックは $mfrSlug='id' を渡す。
     */
    public static function modelDetailCacheKey(string $mfrSlug, string|int $slugForKey): string
    {
        return 'model_detail_'.self::MODEL_DETAIL_CACHE_VERSION."_{$mfrSlug}_{$slugForKey}";
    }

    /**
     * 複数代入可能な属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merged_into_id',
        'name',
        'display_name',
        'is_discontinued',
        'production_end_year',
        'local_image_path',
        'displacement',
        'manufacturer_id',
        'category_id',
        'slug',
        'enriched_content',
        'content_generated_at',
        'model_history',
        'history_generated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'displacement' => 'integer',
        'is_discontinued' => 'boolean',
        'production_end_year' => 'integer',
        'local_image_path' => 'array',
        'enriched_content' => 'array',
        'content_generated_at' => 'datetime',
        'model_history' => 'array',
        'history_generated_at' => 'datetime',
    ];

    /**
     * 表示用の名称を取得（display_name優先、なければname）
     */
    public function displayLabel(): string
    {
        return $this->display_name ?? $this->name;
    }

    /**
     * 画像のフルURLを $bike->image_url で取得できるようにする
     * 実際の掲載車両（Listing）の画像を優先し、なければ local_image_path にフォールバック
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. 代表Listingのローカル画像を優先（確実に表示できる）
                $listing = $this->relationLoaded('representativeListing')
                    ? $this->representativeListing
                    : $this->representativeListing()->first();

                if ($listing) {
                    $localPaths = is_string($listing->local_image_paths)
                        ? json_decode($listing->local_image_paths, true)
                        : $listing->local_image_paths;
                    if (! empty($localPaths) && is_array($localPaths)) {
                        return asset('storage/'.ltrim($localPaths[0], '/'));
                    }
                }

                // 2. BikeModel自身のローカル画像
                if (is_array($this->local_image_path) && ! empty($this->local_image_path)) {
                    $path = ltrim($this->local_image_path[0], '/');

                    return asset('storage/'.$path);
                }

                // 3. ローカル画像を持つ別のListingを探す
                $altListing = $this->listings()
                    ->where('is_sold_out', false)
                    ->whereNotNull('local_image_paths')
                    ->where('local_image_paths', '!=', '[]')
                    ->orderByDesc('id')
                    ->first(['id', 'local_image_paths']);

                if ($altListing) {
                    $altPaths = is_string($altListing->local_image_paths)
                        ? json_decode($altListing->local_image_paths, true)
                        : $altListing->local_image_paths;
                    if (! empty($altPaths) && is_array($altPaths)) {
                        return asset('storage/'.ltrim($altPaths[0], '/'));
                    }
                }

                // 4. 最終手段: 代表Listingの外部URL（GooBike等、404の可能性あり）
                if ($listing) {
                    $imageUrls = is_string($listing->image_urls)
                        ? json_decode($listing->image_urls, true)
                        : $listing->image_urls;
                    if (! empty($imageUrls) && is_array($imageUrls)) {
                        return $imageUrls[0];
                    }
                }

                return null;
            },
        );
    }

    /**
     * 代表となる1件のアクティブなListingを取得（画像フォールバック用）
     * ofMany で効率的なサブクエリJOINを生成（全Listingをロードしない）
     */
    public function representativeListing(): HasOne
    {
        return $this->hasOne(Listing::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $q) => $q->where('is_sold_out', false)
        );
    }

    /**
     * 所属するカテゴリを取得
     */
    public function categoryData(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
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

    /**
     * レビューとのリレーション
     * 承認済み(is_approved=true)のレビューのみを新しい順で取得
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc');
    }

    public function marketStats(): HasOne
    {
        return $this->hasOne(BikeModelMarketStat::class);
    }

    /**
     * 全国の地域別中古相場（中央値）行。お得バッジの基準値に使う。
     * region_block='全国' を絞った hasOne なので、eager load 時は
     * `WHERE bike_model_id IN (...) AND region_block='全国'` の一括取得＝N+1なし。
     */
    public function nationalPriceStat(): HasOne
    {
        return $this->hasOne(ModelRegionPriceStat::class)
            ->where('region_block', config('regions.national_label', '全国'));
    }

    /**
     * YouTube動画とのリレーション
     */
    public function videos(): HasMany
    {
        return $this->hasMany(BikeModelVideo::class)->orderBy('sort_order');
    }

    /**
     * 価格推移ログとのリレーション
     */
    public function priceLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketPriceLog::class)->orderBy('recorded_at', 'asc');
    }

    /**
     * SEOフレンドリーなURLを生成
     * スラッグがあれば /bikes/honda/cb400sf
     * なければ /bikes/honda/123 (IDフォールバック)
     */
    public function getSeoUrlAttribute(): string
    {
        $mfrSlug = $this->manufacturer?->slug;
        $modelSlug = $this->slug;

        if ($mfrSlug && $modelSlug) {
            return "/bikes/{$mfrSlug}/{$modelSlug}";
        }

        // slugがない場合はIDベースフォールバック
        if ($mfrSlug) {
            return "/bikes/{$mfrSlug}/{$this->id}";
        }

        // メーカースラッグもない場合（レアケース）
        return "/bikes/model/{$this->id}";
    }

    /**
     * 統合済み(merged_into_id)なら canonical 側を返す（1ホップ／統合は直接 canonical を指すので連鎖しない）。
     * 統合されていなければ自分自身。モデル解決時の 301・キャッシュパージで使う。
     */
    public function canonicalModel(): self
    {
        if ($this->merged_into_id === null) {
            return $this;
        }

        return self::with('manufacturer')->find($this->merged_into_id) ?? $this;
    }
}
