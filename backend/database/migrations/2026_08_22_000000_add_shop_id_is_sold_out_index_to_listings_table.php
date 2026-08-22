<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // (shop_id, is_sold_out) の複合インデックス。
            // 店舗詳細ページの諸費用集計（WHERE shop_id=? AND is_sold_out=0 …）が、
            // LIMIT 1 付きだと is_sold_out 単独インデックスで全在庫（約168,045行）を range 走査していた。
            // この複合を足すと index_merge の交差が不要になり、LIMIT の有無に関わらず最良の計画（shop_id で即絞り込み）が選ばれる。
            // 既存の listings_shop_id_index (shop_id 単独) は残す（他クエリが利用）。
            $table->index(['shop_id', 'is_sold_out'], 'listings_shop_id_is_sold_out_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_shop_id_is_sold_out_index');
        });
    }
};
