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
        // 既存のテーブルを削除して作り直すか、カラムを追加します
        // ここでは新規作成/再定義の形式で記述します
        Schema::dropIfExists('shops');
        
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('店舗名');
            $table->string('prefecture', 20)->nullable()->comment('都道府県');
            $table->text('address')->nullable()->comment('住所詳細');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->text('website_url')->nullable()->comment('ホームページURL');
            // geometry型が必要な場合は追加（今回はスクレイパーに合わせて省略）
            $table->timestamps();
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