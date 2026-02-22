<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // 累計閲覧数と本日分の閲覧数を追加
            $table->unsignedInteger('view_count_total')->default(0)->after('is_sold_out');
            $table->unsignedInteger('view_count_today')->default(0)->after('view_count_total');
            
            // パフォーマンス向上のためインデックスを付与（人気ランキングなどで利用想定）
            $table->index('view_count_today');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['view_count_total', 'view_count_today']);
        });
    }
};