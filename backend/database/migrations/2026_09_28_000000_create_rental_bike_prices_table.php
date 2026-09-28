<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レンタルバイクの車格別「参考価格」（第2段階）。事業者×車格×プランで1行。
 * ★保険・補償・在庫・画像は持たない。基準プラン（1日/24時間）の基本料金のみ。
 * ★819 は company_slug と vehicle_class が汎用なので、後からスキーマ変更なしで追加できる。
 * ★SQLite テストでも通るよう素の Schema::create（生SQL/ENUM を使わない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_bike_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('company_slug', 50)->comment('yamaha / nirinsho / (後で rental819)');
            $table->string('vehicle_class', 20)->comment('原付 / 125cc / 250cc / 400cc / 大型');
            $table->string('plan', 20)->default('daily')->comment('基準プランキー。当面 daily（1日/24時間相当）');
            $table->string('plan_label', 30)->comment('原文ラベル（24時間 / 1日 など）');
            $table->unsignedInteger('price_yen')->comment('基本料金（税込・保険/補償は含まない）');
            $table->boolean('price_is_from')->default(false)->comment('上位クラスがあり「¥○○〜」表記にする');
            $table->string('note', 100)->nullable()->comment('車種により異なる/季節料金あり 等の補足');
            $table->string('source_url', 255)->comment('取得元の料金ページURL');
            $table->timestamp('fetched_at')->comment('取得日時');
            $table->timestamps();

            $table->unique(['company_slug', 'vehicle_class', 'plan']);
            $table->index('company_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_bike_prices');
    }
};
