<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // 車種ID・販売中・価格の複合インデックス
            // これにより詳細ページの「関連車両(安い順)」が0.01秒になります
            $table->index(['bike_model_id', 'is_sold_out', 'total_price'], 'idx_related_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('idx_related_lookup');
        });
    }
};