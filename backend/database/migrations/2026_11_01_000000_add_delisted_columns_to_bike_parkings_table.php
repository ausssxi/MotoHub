<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bike_parkings に「掲載削除依頼による非公開」を記録する delisted_at / delisted_reason を持たせる。
 *
 * 施設・自治体・管理者の依頼で非公開にしたものを、is_active=false だけでなく明示的に
 * マークしておくための列。狙いは2つ:
 *   1) 記録を残す（いつ・なぜ非公開にしたか）。データは削除せず残す方針。
 *   2) 取り込み（parking:import-jmpsa 等）で誤って公開に戻さない判定材料にする。
 *      ★is_active の自動化は「非公開方向のみ」。公開は人が判断する（CLAUDE.md）。
 *
 * ユーザー投稿の承認待ち（registerParking の is_active=false）と区別するため、
 * 承認待ちでは delisted_at を立てない。delisted_at が立っているものだけが「依頼による削除」。
 * 既存カラムには触れない。両列とも nullable（未削除の行は NULL）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->timestamp('delisted_at')->nullable()->after('is_active')
                ->comment('掲載削除依頼で非公開にした時刻（NULL=依頼削除ではない）');
            $table->string('delisted_reason')->nullable()->after('delisted_at')
                ->comment('掲載削除の理由（依頼元・事情のメモ）');
        });
    }

    public function down(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->dropColumn(['delisted_at', 'delisted_reason']);
        });
    }
};
