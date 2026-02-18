<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 愛車テーブル
        Schema::create('my_bikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // MotoHub内の車種データと紐付ける
            $table->foreignId('bike_model_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name')->comment('愛車のニックネームまたは車種名');
            $table->string('image_url')->nullable()->comment('ユーザー独自の愛車写真');
            
            // ★追加要素
            $table->integer('model_year')->nullable()->comment('年式');
            $table->date('purchased_at')->nullable()->comment('購入日');
            $table->decimal('initial_odometer', 8, 1)->default(0)->comment('購入時の走行距離');
            $table->decimal('current_odometer', 8, 1)->default(0)->comment('現在の総走行距離');
            
            $table->timestamps();
        });

        // 2. 給油記録テーブル
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            
            $table->date('filled_at')->comment('給油日');
            
            // ★修正: 小数点対応
            $table->decimal('odometer', 8, 1)->comment('給油時の総走行距離');
            $table->decimal('quantity', 5, 2)->comment('給油量(L)');
            $table->unsignedInteger('cost')->nullable()->comment('金額(円)');
            
            // ★重要: 満タン法での燃費計算に必須
            $table->boolean('is_full_tank')->default(true)->comment('満タン給油フラグ');
            
            $table->decimal('efficiency', 6, 2)->nullable()->comment('燃費(km/L)');
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['my_bike_id', 'filled_at']);
        });

        // 3. 整備記録テーブル
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            $table->date('maintained_at')->comment('整備日');
            
            // ★修正: 小数点対応
            $table->decimal('odometer', 8, 1)->nullable()->comment('整備時の総走行距離');
            
            $table->string('title')->comment('整備内容');
            $table->unsignedInteger('cost')->nullable()->comment('費用(円)');
            $table->text('note')->nullable()->comment('詳細メモ');
            $table->timestamps();
            
            $table->index(['my_bike_id', 'maintained_at']);
        });
    }

    public function down(): void
    {
        // 削除順序が重要（子テーブルから消す）
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('my_bikes');
    }
};