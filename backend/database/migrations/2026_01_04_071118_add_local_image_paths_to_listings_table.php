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
        Schema::table('listings', function (Blueprint $table) {
            // ローカルに保存した画像の相対パスをJSON形式で保持するカラムを追加
            // image_urls の直後に配置
            $table->json('local_image_paths')->nullable()->after('image_urls')->comment('保存済み画像パス');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('local_image_paths');
        });
    }
};