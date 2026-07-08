<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * オイル適合表の「交換費用の目安」用カラム（レンジ表記の文字列・鮮度管理）。
 * 全て nullable・空で開始（費用は内田さんが実額をCSVで供給。AIは創作しない）。
 * レンジ表記（「900〜1200円」）・「2026-07」表記をそのまま持つため string。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->string('cost_oil_range')->nullable()->after('note_internal');    // オイル代（部品のみ）
            $table->string('cost_shop_range')->nullable()->after('cost_oil_range');  // 用品店 総額
            $table->string('cost_diy_range')->nullable()->after('cost_shop_range');  // DIY 総額
            $table->string('cost_updated_at')->nullable()->after('cost_diy_range');  // 費用の基準時点（鮮度）
        });
    }

    public function down(): void
    {
        Schema::table('model_fitments', function (Blueprint $table) {
            $table->dropColumn(['cost_oil_range', 'cost_shop_range', 'cost_diy_range', 'cost_updated_at']);
        });
    }
};
