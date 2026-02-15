<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browsing_histories', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('listing_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->timestamps();

            // 同じユーザーが同じ車両を重複登録しないようにユニーク制約
            $table->unique(['user_id', 'listing_id']);
            
            // 履歴表示順（新しい順）の高速化用インデックス
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browsing_histories');
    }
};