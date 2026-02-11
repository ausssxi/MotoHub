<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_model_market_stats', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('bike_model_id')
                  ->constrained('bike_models')
                  ->cascadeOnDelete();
            
            // 統計データ (単位: 円)
            // リソース節約のため整数で保存
            $table->unsignedInteger('avg_price')->comment('平均支払総額');
            $table->unsignedInteger('min_price')->comment('最安値');
            $table->unsignedInteger('max_price')->comment('最高値');
            $table->unsignedInteger('listing_count')->comment('集計対象の台数');

            $table->timestamps();
            
            // 検索高速化用
            $table->unique('bike_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_model_market_stats');
    }
};