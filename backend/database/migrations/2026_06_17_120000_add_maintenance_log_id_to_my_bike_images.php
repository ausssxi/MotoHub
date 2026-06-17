<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 整備・カスタム記録への写真添付：my_bike_images に nullable maintenance_log_id を追加（列追加のみ＝安全）。
 * - null  = 愛車ギャラリー画像（カバー/公開で使用。MyBike::images() は whereNull でスコープ）。
 * - notnull= 記録(整備/カスタム)の添付写真（owner-only 専用配信・公開しない）。
 * 記録削除時は FK で DB カスケード＋MaintenanceLog::deleting でモデル経由削除（実ファイルも除去）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_bike_images', function (Blueprint $table) {
            $table->foreignId('maintenance_log_id')->nullable()->after('my_bike_id')
                ->constrained('maintenance_logs')->cascadeOnDelete();
            $table->index(['maintenance_log_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('my_bike_images', function (Blueprint $table) {
            $table->dropForeign(['maintenance_log_id']);
            $table->dropIndex(['maintenance_log_id', 'sort_order']);
            $table->dropColumn('maintenance_log_id');
        });
    }
};
