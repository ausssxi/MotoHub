<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            
            // どの車種へのレビューか
            $table->foreignId('bike_model_id')
                  ->constrained('bike_models')
                  ->cascadeOnDelete();

            // 投稿者情報（今回はログインなしでも投稿可能にするためnullable）
            $table->string('nickname')->default('名無しライダー');
            
            // 評価内容
            $table->string('title'); // タイトル
            $table->text('body');    // 本文
            $table->unsignedTinyInteger('rating')->default(5); // 1〜5の星評価

            // 承認フラグ（荒らし対策用。今回はデフォルトtrueで即公開）
            $table->boolean('is_approved')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};