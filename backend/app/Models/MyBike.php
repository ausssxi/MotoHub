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

    // アクセサ: 表示用の名前
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->bikeModel->name;
    }

    // アクセサ: 表示用の画像URL
    public function getDisplayImageAttribute(): ?string
    {
        // ユーザーが画像を登録できる機能があればそれを優先、なければ車種マスタの画像
        return $this->bikeModel->image_url;
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
}
