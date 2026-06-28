<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 成約判定（cappedSold 条件 #1〜#3）を日次バッチで事前計算して持つフラグ列。
 * リクエスト時の ROW_NUMBER() ウィンドウ（成約約14万行スキャン）を除去するための前提。
 *
 * - is_capped_sold: 条件#1〜#3を満たす行=1（excludeBulkSold は含めない＝直交）。
 *   毎日 listings:compute-capped-sold が更新（デプロイ後は手動で初回バックフィル必須）。
 * - (is_capped_sold, updated_at) 複合索引: boolean等値＋範囲のためオプティマイザが採用する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('is_capped_sold')->default(false)->after('is_sold_out');
            $table->index(['is_capped_sold', 'updated_at'], 'listings_is_capped_sold_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_is_capped_sold_updated_at_index');
            $table->dropColumn('is_capped_sold');
        });
    }
};
