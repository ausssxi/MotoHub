<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // 緯度・経度 (小数点以下を詳しく保存するため double または decimal を使用)
            $table->double('latitude', 10, 7)->nullable()->after('address')->comment('緯度');
            $table->double('longitude', 10, 7)->nullable()->after('latitude')->comment('経度');
            
            // 座標がある店舗を高速に検索するためのインデックス
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};