<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Webikeのバッジ（ジャンル/サービスタグ）を生のまま保持する正本。
            // 例: ["HONDA正規店","公取協加盟店","認証工場","車検受付"]
            $table->json('service_tags')->nullable()->after('regular_holiday');

            // service_tags から導出する分類（shops:classify が更新）。
            // dealer / repair_only / unknown のいずれか。一覧・地図・SEO絞り込み用に索引付与。
            $table->string('shop_type', 20)->nullable()->after('service_tags');
            $table->index('shop_type');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['shop_type']);
            $table->dropColumn(['shop_type', 'service_tags']);
        });
    }
};
