<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MyBike extends Model
{
    use SoftDeletes;

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
        'like_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // 物理削除(forceDelete)時：子（給油/整備/画像）も物理削除し画像実ファイルも消す。
        // FK の DB カスケードは Eloquent イベントを発火しないため、ここで明示的に行う。
        static::deleting(function (MyBike $myBike): void {
            if (! $myBike->isForceDeleting()) {
                return; // 論理削除のカスケードは deleted フックで（親の deleted_at 確定後）
            }
            // 画像はモデル経由で forceDelete（MyBikeImage::deleting が isForceDeleting で実ファイル削除）
            MyBikeImage::withTrashed()->where('my_bike_id', $myBike->id)->get()->each->forceDelete();
            FuelLog::withTrashed()->where('my_bike_id', $myBike->id)->forceDelete();
            MaintenanceLog::withTrashed()->where('my_bike_id', $myBike->id)->forceDelete();
        });

        // 論理削除時：未削除の子だけを親と同一 deleted_at でソフト削除（restore で正確に戻すため）。
        // ※実ファイルは消さない（復元用に保持）。
        static::deleted(function (MyBike $myBike): void {
            if ($myBike->isForceDeleting()) {
                return;
            }
            $ts = $myBike->deleted_at;
            FuelLog::where('my_bike_id', $myBike->id)->whereNull('deleted_at')->update(['deleted_at' => $ts]);
            MaintenanceLog::where('my_bike_id', $myBike->id)->whereNull('deleted_at')->update(['deleted_at' => $ts]);
            MyBikeImage::where('my_bike_id', $myBike->id)->whereNull('deleted_at')->update(['deleted_at' => $ts]);
        });

        // 復元時：このカスケードで一緒に消えた子（deleted_at が親と一致）だけを復元。
        // ＝親削除より前に個別削除された子は復元しない。
        static::restoring(function (MyBike $myBike): void {
            $ts = $myBike->deleted_at; // restore で null 化される前の値
            FuelLog::withTrashed()->where('my_bike_id', $myBike->id)->where('deleted_at', $ts)->update(['deleted_at' => null]);
            MaintenanceLog::withTrashed()->where('my_bike_id', $myBike->id)->where('deleted_at', $ts)->update(['deleted_at' => null]);
            MyBikeImage::withTrashed()->where('my_bike_id', $myBike->id)->where('deleted_at', $ts)->update(['deleted_at' => null]);
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
     * 走行距離の妥当性チェック（OCR/voice/手入力 共通の安全ネット）。
     * ★ブロックはしない・例外も投げない。問題なければ null。
     *
     * $onDate を渡すと「入力日の時系列文脈」で逆行のみ警告する（推奨）。
     *   入力日より前の記録の最大odometer ≤ 入力値 ≤ 入力日より後の記録の最小odometer を満たさないとき警告。
     *   過去日付に小さい値は正常＝警告しない。10倍異常は「直前(=入力日より前の最大)」基準で見直す。
     * $onDate が null の場合のみ従来の running-max(current_odometer) 比較にフォールバックする
     *   （OCR/voice の即時フィードバック等、日付未確定の文脈用）。
     * 編集時は自分自身を $excludeFuelId / $excludeRecordId で除外する。
     *
     * @return string|null 警告文 or null
     */
    public function odometerPlausibilityWarning(
        int|float|null $newOdometer,
        ?string $onDate = null,
        ?int $excludeFuelId = null,
        ?int $excludeRecordId = null
    ): ?string {
        if ($newOdometer === null) {
            return null;
        }

        $new = (float) $newOdometer;
        $mult = (float) config('garage.odometer_jump_multiplier', 5);

        // 日付文脈なし＝従来の running-max 比較（後方互換の安全ネット）。
        if ($onDate === null) {
            $last = (float) $this->current_odometer;
            if ($last <= 0) {
                return null; // 初回・履歴なし
            }

            return $this->odometerJumpText($last, $new, $mult, true);
        }

        // 日付文脈あり＝時系列の逆行のみ警告。
        [$maxBefore, $minAfter] = $this->odometerBoundsByDate($onDate, $excludeFuelId, $excludeRecordId);

        // 前後に記録が無い場合の扱い：
        //  - 記録が1件も無い（登録時の current_odometer のみ）＝running-max にフォールバック（store時の10倍検知）。
        //  - 同日のみ等で前後制約が無い＝時系列の制約なし＝警告しない（境界＝同日は許容）。
        //  - 編集中（自分を除外）の current_odometer は編集対象自身を含み信頼できないためフォールバックしない。
        if ($maxBefore === null && $minAfter === null) {
            if ($excludeFuelId !== null || $excludeRecordId !== null) {
                return null;
            }
            if ($this->hasDatedOdometerRecord()) {
                return null;
            }
            $last = (float) $this->current_odometer;

            return $last > 0 ? $this->odometerJumpText($last, $new, $mult, true) : null;
        }

        if ($minAfter !== null && $new > $minAfter) {
            return '入力日より後の記録（'.$this->formatKm($minAfter).' km）より今回 '.$this->formatKm($new).' km が大きくなっています。確認してください。';
        }

        if ($maxBefore !== null && $new < $maxBefore) {
            return '入力日より前の記録（'.$this->formatKm($maxBefore).' km）より今回 '.$this->formatKm($new).' km が小さくなっています。確認してください。';
        }

        if ($maxBefore !== null && $maxBefore > 0) {
            return $this->odometerJumpText($maxBefore, $new, $mult, false);
        }

        return null;
    }

    /**
     * 走行距離の逆行/大幅増の文言。$backward=true のとき「前回より小さい」も警告する（running-max 用）。
     * 日付文脈では逆行は呼び出し側で判定済みのため $backward=false（10倍異常のみ）。
     */
    private function odometerJumpText(float $base, float $new, float $mult, bool $backward): ?string
    {
        $baseFmt = $this->formatKm($base);
        $newFmt = $this->formatKm($new);

        if ($backward && $new < $base) {
            return "前回 {$baseFmt} km → 今回 {$newFmt} km。走行距離が前回より小さくなっています。確認してください。";
        }

        if ($new > $base * $mult) {
            $msg = "前回 {$baseFmt} km → 今回 {$newFmt} km。前回より大幅に増えています。確認してください。";
            if ($new >= $base * 9.5 && $new <= $base * 10.5) {
                $msg .= '（末尾に端数桁(0.1km)が混ざっていませんか？）';
            }

            return $msg;
        }

        return null;
    }

    /**
     * 入力日 $onDate 文脈での走行距離の上下限を返す（全記録: 給油 + 整備/カスタム）。
     * maxBefore = $onDate「より前」の記録の最大 odometer（null=該当なし）。
     * minAfter  = $onDate「より後」の記録の最小 odometer（null=該当なし）。
     * 同日の記録は前後どちらにも含めない（境界＝同日は警告しない）。
     * 編集時は自分自身を除外する。
     *
     * @return array{0: ?float, 1: ?float} [maxBefore, minAfter]
     */
    private function odometerBoundsByDate(string $onDate, ?int $excludeFuelId, ?int $excludeRecordId): array
    {
        $date = \Illuminate\Support\Carbon::parse($onDate)->toDateString();

        $fuelBefore = FuelLog::where('my_bike_id', $this->id)
            ->whereDate('filled_at', '<', $date)
            ->when($excludeFuelId, fn ($q) => $q->where('id', '!=', $excludeFuelId))
            ->max('odometer');
        $recBefore = MaintenanceLog::where('my_bike_id', $this->id)
            ->whereDate('maintained_at', '<', $date)
            ->when($excludeRecordId, fn ($q) => $q->where('id', '!=', $excludeRecordId))
            ->max('odometer');

        $fuelAfter = FuelLog::where('my_bike_id', $this->id)
            ->whereDate('filled_at', '>', $date)
            ->when($excludeFuelId, fn ($q) => $q->where('id', '!=', $excludeFuelId))
            ->min('odometer');
        $recAfter = MaintenanceLog::where('my_bike_id', $this->id)
            ->whereDate('maintained_at', '>', $date)
            ->when($excludeRecordId, fn ($q) => $q->where('id', '!=', $excludeRecordId))
            ->min('odometer');

        $befores = array_filter([$fuelBefore, $recBefore], fn ($v) => $v !== null);
        $afters = array_filter([$fuelAfter, $recAfter], fn ($v) => $v !== null);

        return [
            $befores === [] ? null : (float) max($befores),
            $afters === [] ? null : (float) min($afters),
        ];
    }

    /**
     * odometer を持つ記録（給油 or 整備/カスタム）が1件でも存在するか。
     * running-max フォールバックを「記録ゼロ＝登録時 current_odometer のみ」の場合に限定するための判定。
     */
    private function hasDatedOdometerRecord(): bool
    {
        return FuelLog::where('my_bike_id', $this->id)->whereNotNull('odometer')->exists()
            || MaintenanceLog::where('my_bike_id', $this->id)->whereNotNull('odometer')->exists();
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
