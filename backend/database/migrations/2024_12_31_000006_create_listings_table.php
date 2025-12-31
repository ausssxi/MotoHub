<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id()->comment('ID (auto_increment)');
            $table->foreignId('bike_model_id')->constrained('bike_models')->comment('車種ID');
            $table->foreignId('shop_id')->constrained('shops')->comment('販売店ID');
            $table->string('source_platform', 50)->comment('取得元サイト名');
            $table->string('source_id', 100)->nullable()->comment('取得元サイトでの固有ID');
            $table->text('source_url')->comment('元記事へのリンク');
            $table->decimal('price', 12, 0)->nullable()->comment('車両本体価格');
            $table->decimal('total_price', 12, 0)->nullable()->comment('支払総額');
            $table->unsignedSmallInteger('model_year')->nullable()->comment('年式 (西暦)');
            $table->unsignedInteger('mileage')->nullable()->comment('走行距離 (km)');
            $table->string('inspection_expiry', 100)->nullable()->comment('車検/自賠責');
            $table->json('image_urls')->nullable()->comment('画像URLの配列');
            $table->text('description')->nullable()->comment('出品説明文');
            $table->boolean('is_sold_out')->default(false)->comment('売り切れフラグ');
            $table->timestamp('created_at')->nullable()->comment('作成日時 (crawled_at兼用)');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};

