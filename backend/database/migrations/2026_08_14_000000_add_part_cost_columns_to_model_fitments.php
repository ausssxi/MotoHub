<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 年間維持費の計算（1回あたりの部品代 ÷ 交換サイクル × 年間走行距離）に使う「数値の部品代レンジ」列。
 *
 * 既存の cost_oil_range / cost_shop_range / cost_diy_range はテキスト表記（「1150〜1550円」）で
 * 表示用途にしか使えないため残したまま、計算用に数値2列を足す。
 * fitment:update-costs コマンドが recommended_part_no で商品検索し、価格の四分位（Q1/Q3）で埋める。
 * 全て nullable・空で開始（未取得の行は null のまま。手入力のテキスト列とは独立）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->unsignedInteger('cost_part_min')->nullable()->after('cost_updated_at'); // 部品代の下限（円・税込・第1四分位）
            $table->unsignedInteger('cost_part_max')->nullable()->after('cost_part_min');   // 部品代の上限（円・税込・第3四分位）
        });
    }

    public function down(): void
    {
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->dropColumn(['cost_part_min', 'cost_part_max']);
        });
    }
};
