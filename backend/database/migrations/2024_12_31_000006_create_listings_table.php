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
            $table->id();
            // bike_model_id も念のため nullable にしておきます
            $table->foreignId('bike_model_id')->nullable()->constrained('bike_models')->onDelete('cascade');
            // shop_id を nullable に変更
            $table->foreignId('shop_id')->nullable()->constrained('shops')->onDelete('set null');
            
            // 車両のタイトルカラムを追加
            $table->string('title')->nullable()->comment('車両タイトル/キャッチコピー');
            
            $table->string('source_platform', 50);
            $table->text('source_url');
            $table->decimal('price', 12, 0)->nullable();
            $table->decimal('total_price', 12, 0)->nullable();
            $table->integer('model_year')->nullable();
            $table->integer('mileage')->nullable();
            $table->json('image_urls')->nullable();
            $table->boolean('is_sold_out')->default(false);
            
            // 作成日時と更新日時にコメントを追加
            $table->timestamp('created_at')->nullable()->comment('作成日時');
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