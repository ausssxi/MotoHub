<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 公開ガレージへの社交コメント（会員限定）。
 * オーナーが「褒められて戻ってくる」＝リテンション強化が狙い。
 * モデレーションは status(published/pending/hidden) キルスイッチ＋ polymorphic reports で。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garage_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 会員限定（ゲスト無し）
            $table->text('body');
            $table->string('status', 20)->default('published'); // published / pending / hidden
            $table->timestamps();

            $table->index(['my_bike_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garage_comments');
    }
};
