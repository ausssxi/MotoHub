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
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id()->comment('ID (auto_increment)');
            $table->string('name', 100)->unique()->comment('メーカー名');
            $table->string('country', 50)->nullable()->comment('原産国');
            // ロゴ画像関連のカラムを追加
            $table->string('logo_url', 255)->nullable()->comment('ロゴ画像URL（外部サイト）');
            $table->string('local_logo_path', 255)->nullable()->comment('ローカル保存用パス');
            
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
    }
};