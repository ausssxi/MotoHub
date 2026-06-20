<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プロフィール表示設定の土台（命名規約 profile_show_*）。
 * - profile_show_parking_reviews: 公開プロフィールに本人の駐車場レビューを表示するか。
 *   Google Local Guide 水準＝デフォルト表示・ユーザーがオフにできる（オプトアウト）ので default true。
 *   将来 SHOP 等を足す時も同じフラット boolean 列で並べる（既存 notify_price_drop と同流儀・JSONにしない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('profile_show_parking_reviews')->default(true)->after('public_token')
                ->comment('公開プロフィールに駐車場レビューを表示（オプトアウト・既定ON）');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_show_parking_reviews');
        });
    }
};
