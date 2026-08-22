<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // is_sold_out は2値のため単独/先頭では絞り込みにならず、以下のクエリが約16.8万行を走査していた。
            // 等値で絞る列を先頭に置いた複合インデックスへ誘導する（既存インデックスは削除しない・追加のみ）。

            // (A) メーカー別一覧: WHERE manufacturer_id=? AND is_sold_out=0 ORDER BY id DESC LIMIT 8。
            //     manufacturer_id+is_sold_out で等値絞り込み後、id 順で先頭数件で打ち切れるようにする。
            $table->index(['manufacturer_id', 'is_sold_out', 'id'], 'listings_manufacturer_id_is_sold_out_id_index');

            // (B) カテゴリ変種の価格集計: WHERE category_id=? AND is_sold_out=0 …で AVG/MIN(total_price)。
            //     total_price を含めて行本体を読まずカバリングにする。
            $table->index(['category_id', 'is_sold_out', 'total_price'], 'listings_category_id_is_sold_out_total_price_index');

            // (C) 排気量帯の車種別集計: WHERE is_sold_out=0 AND displacement BETWEEN ? AND ? GROUP BY bike_model_id。
            //     is_sold_out 等値→displacement 範囲で絞り、bike_model_id・total_price まで含めてカバリングにする。
            $table->index(['is_sold_out', 'displacement', 'bike_model_id', 'total_price'], 'listings_is_sold_out_displacement_bike_model_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_manufacturer_id_is_sold_out_id_index');
            $table->dropIndex('listings_category_id_is_sold_out_total_price_index');
            $table->dropIndex('listings_is_sold_out_displacement_bike_model_id_index');
        });
    }
};
