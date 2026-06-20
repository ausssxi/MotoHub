<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ユーザー間フォロー（ソーシャル②）。follower が followee をフォローする。
 * 一意制約で二重フォロー防止。自己フォローはアプリ層で禁止。FKは cascade（退会で関係も消える）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'followee_id']); // 二重フォロー防止
            $table->index('followee_id'); // フォロワー数の集計/逆引き用
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
