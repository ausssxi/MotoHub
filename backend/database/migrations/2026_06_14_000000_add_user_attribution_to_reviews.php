<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レビューのログインユーザー帰属。
 * - reviews.user_id: ログイン投稿の内部紐付け（nullable・nullOnDelete・index）。
 * - users.review_display_name: 本人が設定した「公開ハンドル」。
 *   ⚠️ 社会ログインの users.name（本名が入りうる）とは別カラム。公開表示はこのカラムのみ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('bike_model_id')
                ->constrained('users')->nullOnDelete();
            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('review_display_name')->nullable()->after('name')
                ->comment('レビュー公開ハンドル（name=本名とは別・公開表示はこれのみ）');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('review_display_name');
        });
    }
};
