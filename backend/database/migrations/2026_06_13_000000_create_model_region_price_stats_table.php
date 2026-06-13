<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 地域別中古相場（中央値）の事前計算テーブル。
 * stats:regional-prices コマンドが (bike_model_id × region_block) ごとに
 * total_price の中央値・件数を投入する。region_block には8地方ブロックに加え
 * 全国集計の '全国' 行も入る。標準的な create table のため driver 分岐は不要。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_region_price_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_model_id')->constrained('bike_models')->cascadeOnDelete();
            $table->string('region_block', 20)->comment('8地方ブロック名 または 全国');
            $table->decimal('median_price', 12, 0)->comment('total_price の中央値（円）');
            $table->unsignedInteger('listing_count')->comment('集計母数（per-shopキャップ後・ゲート5以上のみ保存）');
            $table->timestamp('computed_at')->comment('集計実行時刻');

            $table->unique(['bike_model_id', 'region_block']);
            $table->index('bike_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_region_price_stats');
    }
};
