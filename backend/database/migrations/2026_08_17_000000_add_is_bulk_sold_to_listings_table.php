<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * listings.is_bulk_sold: 一括sold_out除外の事前計算フラグ。
 *
 * ranking:compute-bulk-exclusions が更新し、Listing::scopeExcludeBulkSold が where で参照する。
 * 従来の Redis セット（bulk_sold_exclusion_ids）方式は、除外IDが数千〜数万件に膨れて
 * whereNotIn(...) の SQL が肥大し MySQL が破綻したため、cappedSold の is_capped_sold と同じ
 * フラグ列方式へ移行する。全ページのクエリがこの列で絞るため index を付ける。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('is_bulk_sold')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('is_bulk_sold');
        });
    }
};
