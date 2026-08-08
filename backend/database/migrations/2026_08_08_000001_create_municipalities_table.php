<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 国土数値情報 N03（行政区域）の市区町村メタデータ。
 *
 * pois（コンビニ・GS・洗車場）に city / prefecture が無く住所も27%しか無いため、
 * 座標から市区町村を判定する基盤。ポリゴン本体は municipality_polygons に分離し、
 * こちらは code（全国地方公共団体コード5桁）をキーに表示用の名称類を持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->char('code', 5)->comment('全国地方公共団体コード5桁');
            $table->string('prefecture', 10)->comment('都道府県');
            $table->string('branch', 30)->nullable()->comment('支庁・振興局');
            $table->string('county', 30)->nullable()->comment('郡・政令市');
            $table->string('city_name', 50)->comment('市区町村');
            $table->string('ward', 30)->nullable()->comment('区');
            $table->string('full_name', 100)->comment('county+city_name+ward の連結');
            $table->timestamps();

            $table->primary('code');
            $table->index('prefecture');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
