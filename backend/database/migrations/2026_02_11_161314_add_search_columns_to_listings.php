<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // 検索高速化のために、関連テーブルのIDをlistingsにも持たせる（非正規化）
            $table->unsignedBigInteger('manufacturer_id')->nullable()->after('bike_model_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->after('manufacturer_id')->index();
            
            // 排気量検索も高速化したい場合
            $table->unsignedInteger('displacement')->nullable()->after('category_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['manufacturer_id', 'category_id', 'displacement']);
        });
    }
};