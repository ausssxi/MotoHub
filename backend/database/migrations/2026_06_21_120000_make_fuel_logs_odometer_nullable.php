<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 給油記録の距離を「ソフト必須」化するため fuel_logs.odometer を nullable へ。
 * - 基本フローでは入力（UI/バリデーションで実質必須）。「距離が分からない」選択時のみ NULL 保存可。
 * - 距離なし記録は燃費計算の基準点にしないが給油量は分母に残す。ガソリン代は維持費に算入。
 * backfill 不要（既存は全て値あり）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('odometer', 8, 1)->nullable()->comment('給油時の総走行距離（距離不明時は NULL）')->change();
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('odometer', 8, 1)->nullable(false)->comment('給油時の総走行距離')->change();
        });
    }
};
