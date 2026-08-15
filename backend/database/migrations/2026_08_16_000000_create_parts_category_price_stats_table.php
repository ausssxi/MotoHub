<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * パーツカテゴリごとの実勢価格統計を保存するテーブル。
 *
 * config/parts-categories.php の price_range は全カテゴリでベタ書きのため、実際の商品価格（楽天/Yahoo）から
 * 四分位で算出した値に置き換えるための保存先。極端な外れ値（数千円のガスケット〜数十万円のチタンフルエキが
 * 同一検索に混在）に引きずられないよう、表示に使うのは min/max ではなく四分位（Q1/中央値/Q3）。min/max は参考。
 * 集計は stats コマンド parts:compute-category-prices が行う。ページ側の表示切替は別タスク。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_category_price_stats', function (Blueprint $table) {
            $table->id();

            $table->string('category_slug')->comment('parts-categories.php の slug（カテゴリのキー）');

            $table->unsignedInteger('product_count')->comment('重複除去後の有効価格つき商品数');
            $table->unsignedInteger('price_q1')->comment('第1四分位（表示用の下限）');
            $table->unsignedInteger('price_median')->comment('中央値');
            $table->unsignedInteger('price_q3')->comment('第3四分位（表示用の上限）');
            $table->unsignedInteger('price_min')->comment('最安値（参考。外れ値に注意）');
            $table->unsignedInteger('price_max')->comment('最高値（参考。外れ値に注意）');

            $table->timestamp('computed_at')->comment('集計日時');

            // カテゴリ1行。表示側は category_slug で1件引く。
            $table->unique('category_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_category_price_stats');
    }
};
