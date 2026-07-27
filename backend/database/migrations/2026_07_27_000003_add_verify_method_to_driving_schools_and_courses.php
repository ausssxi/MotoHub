<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * driving_schools / driving_school_courses に verify_method（確認方法）を追加する。
 *
 * 機械一次判定を採用するにあたり、verified_at（確認日）とは別に「誰が確認したか」を分離する。
 * verified_at に「人が確認した日」と「機械が確認した日」を混ぜると、後で信頼度で選別できなくなる
 * （status を verified_at に混ぜて事故になったのと同じ構造の間違い）ため、最初から列を分ける。
 *
 * verify_method の値:
 *  - human   … 人が公式サイト等を読んで確認した（信頼度高）。既存の全行はこれ。
 *  - machine … 機械が公式サイトを読んで判定した（信頼度は human より一段低い。棚卸しで優先確認）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->string('verify_method', 10)->default('human')->after('status'); // human / machine
        });

        Schema::table('driving_school_courses', function (Blueprint $table) {
            $table->string('verify_method', 10)->default('human')->after('verified_at'); // human / machine
        });
    }

    public function down(): void
    {
        Schema::table('driving_schools', function (Blueprint $table) {
            $table->dropColumn('verify_method');
        });

        Schema::table('driving_school_courses', function (Blueprint $table) {
            $table->dropColumn('verify_method');
        });
    }
};
