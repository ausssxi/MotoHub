<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shop_submissions.image_path: 投稿された店舗写真の「非公開ディスク上のパス」。
 * 承認前は public に出さない（管理者の目視承認を挟むまで公開URL不到達）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
