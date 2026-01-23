<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            // address の unique インデックスを削除します
            // インデックス名はデフォルトで 'shops_address_unique' です
            $blueprint->dropUnique(['address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            // 戻す場合は再度 unique を設定（ただし重複があると失敗します）
            $blueprint->string('address', 255)->unique()->change();
        });
    }
};