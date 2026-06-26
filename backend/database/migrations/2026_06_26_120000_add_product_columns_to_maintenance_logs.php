<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カスタム記録（maintenance_logs, type=custom）の商品連携（第2段階2a）。
 * 全て add-only nullable。既存の表示/集計（ledger / installed_parts / CSV）は
 * 明示カラム選択をしておらず影響なし。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->string('product_mall', 10)->nullable()->after('is_installed')->comment('rakuten / yahoo');
            $table->string('product_id')->nullable()->after('product_mall')->comment('モール商品コード（2bの再取得キー）');
            $table->string('product_name')->nullable()->after('product_id')->comment('商品名スナップショット');
            $table->string('product_image_url', 512)->nullable()->after('product_name')->comment('画像URL（ホットリンク・保存しない）');
            $table->unsignedInteger('product_price')->nullable()->after('product_image_url')->comment('価格スナップショット（円）');
            $table->string('product_url', 1024)->nullable()->after('product_price')->comment('アフィリエイト済みクリックURL');
            $table->timestamp('product_price_fetched_at')->nullable()->after('product_url')->comment('価格取得日時（2bの鮮度判定用）');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'product_mall',
                'product_id',
                'product_name',
                'product_image_url',
                'product_price',
                'product_url',
                'product_price_fetched_at',
            ]);
        });
    }
};
