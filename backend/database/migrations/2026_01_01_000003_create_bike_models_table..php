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
            // モデル名に UNIQUE KEY を追加しました
            $table->string('name', 255)->unique()->comment('モデル名');
            $table->unsignedSmallInteger('displacement')->comment('排気量 (cc)');
            $table->string('category', 50)->nullable()->comment('カテゴリ');
            $table->timestamp('created_at')->nullable()->comment('作成日時');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');
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