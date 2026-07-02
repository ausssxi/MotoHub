<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shop_submissions.target_shop_id: 既存店を対象にした投稿（詳細ページからの公式URL提案）を示す。
 * null = 通常の新規店投稿（従来通り）。値あり = その既存店への追加情報（URL）提案。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->foreignId('target_shop_id')->nullable()->after('linked_shop_id')
                ->constrained('shops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_submissions', function (Blueprint $table) {
            $table->dropForeign(['target_shop_id']);
            $table->dropColumn('target_shop_id');
        });
    }
};
