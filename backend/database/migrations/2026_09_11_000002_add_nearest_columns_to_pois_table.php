<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pois に「最も近い同種別POI」の事前計算結果を持たせる。
 *
 * 表示時に空間クエリを投げず（16,546件のGS・31,050件のコンビニ詳細ページを支えるため）、
 * 夜間バッチ poi:compute-nearest が1回計算して書き戻す。すべて nullable。
 *
 * nearest_computed_at は resume 用: NULL の行だけを処理し、途中で止めても続きから走る。
 * 既存カラムには一切手を触れない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->unsignedBigInteger('nearest_same_type_id')->nullable()->after('municipality_code')
                ->comment('最も近い同種別POIのid');
            $table->unsignedInteger('nearest_same_type_m')->nullable()->after('nearest_same_type_id')
                ->comment('最も近い同種別POIまでの距離（メートル）');
            $table->timestamp('nearest_computed_at')->nullable()->after('nearest_same_type_m')
                ->comment('最近傍を計算した時刻（NULL=未計算・resume対象）');

            // resume スキャン（type 絞り + 未計算抽出）用。
            $table->index(['type', 'nearest_computed_at']);
            // 孤立POI抽出（サイトマップ選別 nearest_same_type_m >= 3000）用。
            $table->index(['type', 'nearest_same_type_m']);
        });
    }

    public function down(): void
    {
        Schema::table('pois', function (Blueprint $table) {
            $table->dropIndex(['type', 'nearest_computed_at']);
            $table->dropIndex(['type', 'nearest_same_type_m']);
            $table->dropColumn(['nearest_same_type_id', 'nearest_same_type_m', 'nearest_computed_at']);
        });
    }
};
