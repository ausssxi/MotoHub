<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * パーツカテゴリページに表示する商品カードを保存するテーブル。
 *
 * parts:compute-category-prices が価格統計を算出する際に取得した楽天/Yahoo の商品配列から、上位N件を
 * 選んで保存する（追加のAPI呼び出しは行わない）。表示側はこのテーブルを読むだけ（ライブAPIを叩かない）。
 * parts_category_price_stats と同じ流儀で、専用モデルは作らず DB::table で読み書きする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_category_products', function (Blueprint $table) {
            $table->id();

            $table->string('category_slug', 64)->index()->comment('parts-categories.php の slug');
            $table->unsignedTinyInteger('rank')->comment('表示順（1..N。API関連度順を維持した採番）');
            $table->string('source', 16)->comment('rakuten | yahoo');
            $table->string('title', 512);
            $table->unsignedInteger('price');
            $table->string('shop_name', 255)->nullable();
            $table->text('product_url');
            $table->text('image_url')->nullable();
            $table->dateTime('fetched_at')->comment('取得時刻（parts_category_price_stats.computed_at と同時刻）');

            $table->timestamps();

            $table->unique(['category_slug', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_category_products');
    }
};
