<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受け入れ情報の投稿者表示名。
 * Review の nickname と同じく「表示名スナップショット」。ログイン時は
 * users.review_display_name（公開ハンドル）のスナップショット、匿名時は入力値。
 * ⚠️ users.name（社会ログインの本名が入りうる）は絶対に使わない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->string('submitter_name')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('shop_acceptance_reports', function (Blueprint $table) {
            $table->dropColumn('submitter_name');
        });
    }
};
