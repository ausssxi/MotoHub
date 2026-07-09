<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 即反映系（reviews は is_approved default true・parking_reviews は承認カラム無し）の
 * スパム防御を acceptance/submission と同水準へ。生IPは保存せず sha256(ip|app.key) のみ。
 * 連投・濫用の単位／将来の事後対応用（honeypot は FormRequest 側で追加）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('submitter_ip_hash', 64)->nullable()->after('nickname');
        });

        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->string('submitter_ip_hash', 64)->nullable()->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('submitter_ip_hash');
        });
        Schema::table('parking_reviews', function (Blueprint $table) {
            $table->dropColumn('submitter_ip_hash');
        });
    }
};
