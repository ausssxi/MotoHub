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
        // 愛車削除時に全画像（ギャラリー＋記録添付）を「モデル経由」で削除し実ファイルも消す。
        // （FKのDBカスケードはMyBikeImage::deletingを発火させないため、ここで明示的にループする）
        static::deleting(function (MyBike $myBike): void {
            MyBikeImage::where('my_bike_id', $myBike->id)->get()->each->delete();
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

    /**
     * 走行距離の前回比 妥当性チェック（OCR/voice/手入力 共通の安全ネット）。
     * 前回 L = current_odometer（running max）。今回値が L未満／L×倍率超 のとき確認文を返す。
     * ★ブロックはしない・例外も投げない。問題なければ null。
     *
     * @return string|null 警告文（前回Lと今回newを必ず含む）or null
     */
    public function odometerPlausibilityWarning(int|float|null $newOdometer): ?string
    {
        if ($newOdometer === null) {
            return null;
        }

        $last = (float) $this->current_odometer;
        if ($last <= 0) {
            return null; // 初回・履歴なし
        }

        $new = (float) $newOdometer;
        $mult = (float) config('garage.odometer_jump_multiplier', 5);
        $lastFmt = $this->formatKm($last);
        $newFmt = $this->formatKm($new);

        if ($new < $last) {
            return "前回 {$lastFmt} km → 今回 {$newFmt} km。走行距離が前回より小さくなっています。確認してください。";
        }

        if ($new > $last * $mult) {
            $msg = "前回 {$lastFmt} km → 今回 {$newFmt} km。前回より大幅に増えています。確認してください。";
            if ($new >= $last * 9.5 && $new <= $last * 10.5) {
                $msg .= '（末尾に端数桁(0.1km)が混ざっていませんか？）';
            }

            return $msg;
        }

        return null;
    }

    private function formatKm(float $v): string
    {
        return $v == floor($v) ? (string) (int) $v : (string) $v;
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

    // 整備記録（type=maintenance のみ）。リマインダー/維持費/CSV/public_show はこれを使う＝
    // custom はリマインダーに混入せず public にも漏れない（既存挙動を保ったまま custom を同居）。
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)
            ->where('type', MaintenanceLog::TYPE_MAINTENANCE)
            ->orderBy('maintained_at', 'desc');
    }

    // カスタム記録（type=custom）。「今ついてる装備」等で使用。
    public function customRecords(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)
            ->where('type', MaintenanceLog::TYPE_CUSTOM)
            ->orderBy('maintained_at', 'desc');
    }

    // ギャラリー画像（private・本人のみ表示・カバー/公開で使用）。記録添付写真は除外。
    public function images(): HasMany
    {
        return $this->hasMany(MyBikeImage::class)
            ->whereNull('maintenance_log_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
