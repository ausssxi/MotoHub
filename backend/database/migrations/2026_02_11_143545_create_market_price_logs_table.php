<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_price_logs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('bike_model_id')
                  ->constrained('bike_models')
                  ->cascadeOnDelete();
            
            // その時点での統計データ
            $table->unsignedInteger('avg_price')->comment('平均価格');
            $table->unsignedInteger('min_price')->comment('最安値');
            $table->unsignedInteger('max_price')->comment('最高値');
            $table->unsignedInteger('listing_count')->comment('集計時の台数');
            
            // 集計日（年月単位で集計する場合は1日として保存）
            $table->date('recorded_at')->index();
            
            $table->timestamps();

            // 同じ日に同じ車種のデータは1つだけ
            $table->unique(['bike_model_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_logs');
    }
};