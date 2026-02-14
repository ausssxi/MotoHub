<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            
            // ユーザーID
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // 車両ID
            $table->foreignId('listing_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->timestamps();

            // 同じユーザーが同じ車両を重複登録しないようにユニーク制約
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};