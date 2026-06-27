<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 外部パートナー向けデータAPIのAPIキー（第1段階・限定提供）。
 * キーは平文を保存せず SHA-256 ハッシュで保持（DB流出時もキーが漏れない）。
 * 発行は motohub:api-key-issue コマンド（平文は発行時に一度だけ表示）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label')->comment('発行先メモ（例: ビークルビーグル）');
            $table->string('key_prefix', 16)->comment('識別用の先頭文字（平文ではない）');
            $table->string('key_hash', 64)->unique()->comment('キーのSHA-256ハッシュ');
            $table->boolean('is_active')->default(true)->comment('有効/無効（無効化で即停止）');
            $table->timestamp('last_used_at')->nullable()->comment('最終利用日時');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
