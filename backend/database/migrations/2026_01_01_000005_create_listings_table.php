<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            // 先に作成されている bike_models, shops, sites を参照
            $table->foreignId('bike_model_id')->nullable()->constrained('bike_models')->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained('shops')->onDelete('set null');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade')->comment('取得元サイトID');
            
            $table->string('title')->nullable()->comment('車両タイトル/キャッチコピー');
            $table->text('source_url');
            $table->decimal('price', 12, 0)->nullable();
            $table->decimal('total_price', 12, 0)->nullable();
            $table->integer('model_year')->nullable();
            $table->integer('mileage')->nullable();
            $table->json('image_urls')->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->timestamp('created_at')->nullable()->comment('作成日時');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};