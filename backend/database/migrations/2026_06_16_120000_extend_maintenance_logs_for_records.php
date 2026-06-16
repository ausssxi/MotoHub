<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 整備・カスタム記録の統合基盤：既存 maintenance_logs を拡張する（新規並行テーブルは作らない）。
 * - 追加のみ（既存列は改変しない＝本番安全）。既存行は type のDB既定で maintenance になる。
 * - maintained_at は recorded_at(実施日) の実体としてそのまま使用。odometer は既存 decimal(8,1) 維持。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            // 共通: 種別（maintenance / custom ／将来 expense）。既存行は maintenance。
            $table->string('type', 20)->default('maintenance')->after('my_bike_id')->comment('maintenance/custom/(future)expense');
            // 共通: 店名 or DIY、支払方法。費用は既存 cost を維持費へ。
            $table->string('vendor')->nullable()->after('cost')->comment('店名 or DIY');
            $table->string('payment_method')->nullable()->after('vendor');
            // 共通: 記録ごとの公開フラグ（visibilityフック）。今回サーフェスは作らない。
            // 公開されるのは将来 bike.is_public && record.is_public の AND（カバー公開と同じ防御的既定）。
            $table->boolean('is_public')->default(false)->after('note');

            // custom 専用（type=custom のときのみ使用）
            $table->string('part_name')->nullable()->after('is_public')->comment('custom: パーツ名');
            $table->string('brand')->nullable()->after('part_name')->comment('custom: ブランド');
            $table->string('category')->nullable()->after('brand')->comment('custom: 外装/排気/吸気/電装/足回り/その他');
            $table->boolean('is_installed')->default(true)->after('category')->comment('custom: 現在装着中か');

            $table->index(['my_bike_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropIndex(['my_bike_id', 'type']);
            $table->dropColumn([
                'type', 'vendor', 'payment_method', 'is_public',
                'part_name', 'brand', 'category', 'is_installed',
            ]);
        });
    }
};
