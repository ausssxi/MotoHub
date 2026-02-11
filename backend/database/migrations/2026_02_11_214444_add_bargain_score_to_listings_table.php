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
            // お買い得度スコア (平均価格より何%安いか) を保存するカラム
            // 例: 相場より20.5%安ければ 20.5 が入る
            $table->decimal('bargain_score', 5, 2)->default(0)->after('displacement');
            
            // 検索とソートを爆速にするためのインデックス
            $table->index(['is_sold_out', 'bargain_score'], 'idx_active_bargain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('idx_active_bargain');
            $table->dropColumn('bargain_score');
        });
    }
};