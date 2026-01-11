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
        // 既存のテーブルを削除して作り直す形式
        Schema::dropIfExists('shops');
        
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('店舗名');
            $table->string('prefecture', 20)->nullable()->comment('都道府県');
            $table->string('address', 255)->unique()->comment('住所詳細');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->text('website_url')->nullable()->comment('ホームページURL');
            
            // 評価・営業時間
            $table->decimal('rating', 3, 1)->nullable()->default(0.0)->comment('評価点 (0.0〜5.0)');
            $table->string('business_hours', 255)->nullable()->comment('営業時間');
            $table->string('regular_holiday', 255)->nullable()->comment('定休日');

            // 店舗画像関連のカラムを追加
            $table->string('image_url', 255)->nullable()->comment('店舗画像URL（外部サイト）');
            $table->string('local_image_path', 255)->nullable()->comment('ローカル保存用パス');

            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};