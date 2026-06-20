<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ソーシャル②のカウンタキャッシュ列（既存 likes_count/picks_count と同流儀＝都度countより軽い）。
 * - users.followers_count / following_count: フォロワー数・フォロー中数（公開）。
 * - my_bikes.like_count: 公開ガレージのいいね数（公開）。
 * いずれも toggle 時に increment/decrement する。default 0・backfill 不要（新規関係から積む）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('followers_count')->default(0)->after('profile_show_parking_reviews');
            $table->unsignedInteger('following_count')->default(0)->after('followers_count');
        });

        Schema::table('my_bikes', function (Blueprint $table) {
            $table->unsignedInteger('like_count')->default(0)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['followers_count', 'following_count']);
        });
        Schema::table('my_bikes', function (Blueprint $table) {
            $table->dropColumn('like_count');
        });
    }
};
