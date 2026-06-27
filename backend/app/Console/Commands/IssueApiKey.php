<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 外部データAPI用のAPIキーを発行する（限定提供・手動発行）。
 * 平文キーはこの発行時に一度だけ表示。DBには SHA-256 ハッシュのみ保存する。
 *
 * 例: php artisan motohub:api-key-issue "ビークルビーグル"
 */
final class IssueApiKey extends Command
{
    protected $signature = 'motohub:api-key-issue {label : 発行先メモ（例: ビークルビーグル）}';

    protected $description = '外部データAPIのAPIキーを発行（平文は発行時に一度だけ表示・DBはハッシュ保存）';

    public function handle(): int
    {
        $label = (string) $this->argument('label');

        $key = 'mh_live_'.Str::random(48);
        $apiKey = ApiKey::create([
            'label' => $label,
            'key_prefix' => substr($key, 0, 16),
            'key_hash' => ApiKey::hashKey($key),
            'is_active' => true,
        ]);

        $this->info("APIキーを発行しました（id={$apiKey->id} / 発行先: {$label}）。");
        $this->warn('▼ 平文キーはこの一度だけ表示されます。安全に控えて相手へ渡してください。');
        $this->line('');
        $this->line("    {$key}");
        $this->line('');
        $this->line('利用例:');
        $this->line("    curl -H 'X-API-Key: {$key}' 'https://motohub.jp/api/v1/rankings/listings?class=250'");
        $this->line('無効化（停止）: api_keys.is_active = false に更新（即停止）。');

        return self::SUCCESS;
    }
}
