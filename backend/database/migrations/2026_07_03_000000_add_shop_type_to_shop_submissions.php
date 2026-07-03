<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shop_submissions.shop_type: 投稿者が申告する店種（dealer|repair_only|unknown）。
 * 承認時に shops.shop_type へ反映（null は unknown）。入口が /shops/area まで拡大し
 * 販売店の投稿も来るため、repair_only 固定をやめて投稿から受け取る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->string('shop_type', 20)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->dropColumn('shop_type');
        });
    }
};
