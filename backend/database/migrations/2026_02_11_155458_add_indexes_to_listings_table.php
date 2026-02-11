<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // 検索・絞り込みによく使うカラムにインデックスを追加
            $table->index('is_sold_out'); // 販売中かどうかの判定用
            $table->index('bike_model_id'); // 車種検索用
            $table->index('shop_id'); // 店舗検索用
            $table->index('total_price'); // 価格ソート・絞り込み用
            $table->index('mileage'); // 走行距離ソート用
            $table->index('model_year'); // 年式ソート用
            $table->index('created_at'); // 新着順ソート用
            
            // 複合インデックス（「販売中の車種」を一発で引くため）
            $table->index(['is_sold_out', 'bike_model_id']);
            $table->index(['is_sold_out', 'total_price']);
        });
        
        Schema::table('bike_models', function (Blueprint $table) {
            $table->index('manufacturer_id');
            $table->index('category_id');
            $table->index('name'); // 名前検索用
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['is_sold_out']);
            $table->dropIndex(['bike_model_id']);
            $table->dropIndex(['shop_id']);
            $table->dropIndex(['total_price']);
            $table->dropIndex(['mileage']);
            $table->dropIndex(['model_year']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_sold_out', 'bike_model_id']);
            $table->dropIndex(['is_sold_out', 'total_price']);
        });

        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropIndex(['manufacturer_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['name']);
        });
    }
};