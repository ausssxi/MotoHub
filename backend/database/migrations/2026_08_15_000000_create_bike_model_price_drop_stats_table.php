<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車種ごとの値下げ統計を保存するテーブル。
 *
 * 用途は「待てば下がるのか」という買い手の関心に答える車種単位の集計で、既存 bike_model_market_stats
 * （相場価格の集計）とは目的が異なるため別テーブルにする（同じテーブルに列を足すと、どちらの集計が
 * いつ更新されるか分からなくなるため）。集計は stats:model-price-drops コマンドが行う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_model_price_drop_stats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bike_model_id')
                ->constrained('bike_models')
                ->cascadeOnDelete();

            $table->unsignedInteger('listing_count')
                ->comment('集計対象の掲載数（since_date以降にMotoHubが確認した掲載）');
            $table->unsignedInteger('dropped_listing_count')
                ->comment('うち条件を満たす値下げを経験した掲載数');
            // listings.created_at は販売店の掲載日ではなくMotoHubの初回取得日。値下げ0件の車種では null。
            $table->unsignedInteger('avg_first_drop_days')->nullable()
                ->comment('初回値下げまでの平均日数（listings.created_at=MotoHub初回取得日 起点）');
            $table->unsignedInteger('avg_drop_amount')->nullable()
                ->comment('1回あたりの平均値下げ額（円）');
            $table->decimal('avg_drop_rate', 4, 1)->nullable()
                ->comment('1回あたりの平均値下げ率（％）');

            $table->timestamp('computed_at')->comment('集計日時');

            // 車種1行。表示側は bike_model_id で1件引く。
            $table->unique('bike_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_model_price_drop_stats');
    }
};
