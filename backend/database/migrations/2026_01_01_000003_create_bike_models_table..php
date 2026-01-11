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
        Schema::create('bike_models', function (Blueprint $table) {
            $table->id()->comment('ID (auto_increment)');
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->comment('メーカーID');
            $table->string('name', 255)->unique()->comment('モデル名');
            $table->integer('displacement')->nullable()->comment('排気量 (cc)');
            $table->string('category', 50)->nullable()->comment('カテゴリ');
            
            // モデルの画像関連カラムを追加
            $table->string('image_url', 255)->nullable()->comment('モデル画像URL（外部サイト）');
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
        Schema::dropIfExists('bike_models');
    }
};