<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bike_models と manufacturers にスラッグカラムを追加
 * 
 * 目的: SEOフレンドリーなURL構造を実現
 *   旧: /bikes/model/123
 *   新: /bikes/honda/cb400sf
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. manufacturers に slug を追加
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('slug', 100)
                  ->nullable()
                  ->after('name')
                  ->comment('URL用スラッグ (例: honda, yamaha, kawasaki)');
            
            $table->unique('slug', 'manufacturers_slug_unique');
        });

        // 2. bike_models に slug を追加
        Schema::table('bike_models', function (Blueprint $table) {
            $table->string('slug', 150)
                  ->nullable()
                  ->after('name')
                  ->comment('URL用スラッグ (例: cb400sf, ninja-250)。日本語名の場合はnull');

            // manufacturer_id + slug の複合ユニーク
            // 同一メーカー内でスラッグが重複しないようにする
            $table->unique(['manufacturer_id', 'slug'], 'bike_models_mfr_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropUnique('bike_models_mfr_slug_unique');
            $table->dropColumn('slug');
        });

        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropUnique('manufacturers_slug_unique');
            $table->dropColumn('slug');
        });
    }
};