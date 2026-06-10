<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 重複モデル統合(dedup)用。値がセットされた行は「canonical へ統合済み＝無効」を表し、
     * モデル解決時は canonical へ 301、各種一覧/サイトマップ/検索からは除外する。
     * 全行 null で投入するため非破壊。
     */
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('id');
            $table->foreign('merged_into_id')->references('id')->on('bike_models')->nullOnDelete();
            $table->index('merged_into_id');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropIndex(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });
    }
};
