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
        Schema::create('shop_identifiers', function (Blueprint $table) {
            $table->id();
            // shopsテーブルへの外部キー
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade')->comment('販売店ID');
            // sitesテーブルへの外部キー
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade')->comment('サイトID');
            // サイト固有の店舗認識番号 (例: GooBikeの '8502604' など)
            $table->string('identifier', 100)->comment('サイト固有の店舗認識番号');
            
            $table->timestamp('created_at')->nullable()->comment('作成日時');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');

            // 同じサイト内で同じ識別番号が重複しないようにユニーク制約
            $table->unique(['site_id', 'identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_identifiers');
    }
};