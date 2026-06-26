<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 外部パートナー向けデータAPIのAPIキー（平文は保持せず SHA-256 ハッシュで照合）。
 */
final class ApiKey extends Model
{
    protected $fillable = [
        'label',
        'key_prefix',
        'key_hash',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /** 平文キーから SHA-256 ハッシュを作る（保存・照合で共通利用）。 */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
