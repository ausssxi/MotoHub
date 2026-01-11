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
            // 外部キー
            $table->foreignId('bike_model_id')->nullable()->constrained('bike_models')->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained('shops')->onDelete('set null');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade')->comment('取得元サイトID');
            
            // 車両基本情報
            $table->string('title')->nullable()->comment('車両タイトル/キャッチコピー');
            $table->text('source_url')->comment('元サイトURL');
            $table->decimal('price', 12, 0)->nullable()->comment('本体価格');
            $table->decimal('total_price', 12, 0)->nullable()->comment('支払総額');
            $table->integer('model_year')->nullable()->comment('モデル年式');
            $table->integer('mileage')->nullable()->comment('走行距離(km)');
            
            // 車両詳細スペック（追加分）
            $table->string('first_registration')->nullable()->comment('初年度登録');
            $table->boolean('has_repair_history')->default(false)->comment('修復歴の有無');
            $table->string('condition')->nullable()->comment('車両コンディション(新車/中古)');
            $table->string('color')->nullable()->comment('カラー/色');
            
            // 画像関連
            $table->json('image_urls')->nullable()->comment('元サイトの画像URLリスト');
            $table->json('local_image_paths')->nullable()->comment('サーバーに保存した画像パス');
            
            // ステータスと時間
            $table->boolean('is_sold_out')->default(false)->comment('完売フラグ');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新日時');
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