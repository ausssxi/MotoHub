<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 公開ガレージ（MyBike）へのいいね（ソーシャル②）。最小スコープ＝ガレージ専用（polymorphicにしない）。
 * 一意制約で二重いいね防止。自分のガレージへのいいねはアプリ層で禁止。FKは cascade。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garage_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'my_bike_id']); // 二重いいね防止
            $table->index('my_bike_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_likes');
    }
};
