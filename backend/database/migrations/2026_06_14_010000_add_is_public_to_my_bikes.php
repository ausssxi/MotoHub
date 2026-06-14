<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 愛車ガレージの台ごと公開フラグ。デフォルト非公開（consent優先）。
 * ⚠️ 既存の愛車は全て is_public=false へ＝意思確認なく全公開だった状態を是正し、
 *   公開は持ち主の明示 opt-in を取り直す（grandfather せず revert-to-private）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('my_bikes', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('bike_model_id')
                ->comment('公開フラグ。デフォルト非公開・台ごとの明示opt-in');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('my_bikes', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
