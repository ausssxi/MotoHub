<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MyBike extends Model
{
    protected $fillable = [
        'user_id',
        'bike_model_id',
        'is_public',
        'name',
        'model_year',     // 年式
        'purchased_at',   // 購入日
        'initial_odometer',
        'current_odometer', // 現在の走行距離
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        // 愛車削除時にギャラリー画像を「モデル経由」で削除し実ファイルも消す。
        // （FKのDBカスケードはMyBikeImage::deletingを発火させないため、ここで明示的にループする）
        static::deleting(function (MyBike $myBike): void {
            $myBike->images()->get()->each->delete();
        });
    }

    // アクセサ: 表示用の名前
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->bikeModel->name;
    }

    // アクセサ: カバー画像URL（private/public 共通）。
    // 解決順は「明示カバー(image_url) → ギャラリー1枚目 → カタログ画像」、無ければ null（→プレースホルダ）。
    // ギャラリー1枚目は owner-check 配信ルートのURL。配信側の許可で
    //  - 所有者は全画像200
    //  - 非所有者は is_public ガレージのカバー(=1枚目)のみ200
    // となるため、public面（public_show / public_index / みんなの愛車 / オーナー一覧）には
    // is_public のガレージだけを並べる前提（各クエリで is_public フィルタ済み）であれば
    // カバーは常に200で表示できる。
    public function getDisplayImageAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        // images() は sort_order asc, id asc 済み → 先頭が「1枚目（カバー）」。
        $first = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();
        if ($first) {
            return route('mybikes.images.show', [$this->id, $first->id]);
        }

        return $this->bikeModel?->image_url;
    }

    /**
     * 追加: 最新の燃費を取得するアクセサ
     * Bladeでの使用法: {{ $bike->latest_efficiency }}
     */
    public function getLatestEfficiencyAttribute(): ?float
    {
        // fuelLogsはリレーションで既に降順ソートされていますが、
        // 念のためコレクションから「燃費(efficiency)が入っている」最新のものを探します
        $latestLog = $this->fuelLogs
            ->whereNotNull('efficiency') // 燃費が計算されていない(null)記録を除外
            ->first(); // 先頭（最新）を取得

        return $latestLog ? (float) $latestLog->efficiency : null;
    }

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 給油記録
    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class)
            ->orderBy('filled_at', 'desc') // 給油日の新しい順
            ->orderBy('id', 'desc');       // 同日の場合は登録順（ID順）で並べる
    }

    // 整備記録
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->orderBy('maintained_at', 'desc');
    }

    // ギャラリー画像（private・本人のみ表示）。並び順 → 登録順。
    public function images(): HasMany
    {
        return $this->hasMany(MyBikeImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
