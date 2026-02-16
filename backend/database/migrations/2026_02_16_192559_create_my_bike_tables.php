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
            
            // MotoHub内の車種データと紐付ける（任意）
            $table->foreignId('bike_model_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name')->comment('愛車のニックネームまたは車種名');
            $table->string('image_url')->nullable()->comment('愛車の写真');
            $table->unsignedInteger('odometer')->default(0)->comment('現在の総走行距離');
            
            $table->timestamps();
        });

        // 2. 給油記録テーブル
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            
            $table->date('filled_at')->comment('給油日');
            $table->unsignedInteger('odometer')->comment('給油時の総走行距離');
            $table->decimal('quantity', 5, 2)->comment('給油量(L)');
            $table->unsignedInteger('cost')->nullable()->comment('金額(円)');
            
            // 燃費計算用（前回給油時との差分で計算して保存）
            $table->decimal('efficiency', 5, 2)->nullable()->comment('燃費(km/L)');
            
            $table->string('memo')->nullable();
            $table->timestamps();

            // 日付順に並べるためのインデックス
            $table->index(['my_bike_id', 'filled_at']);
        });

        // 3. 整備記録テーブル
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained('my_bikes')->cascadeOnDelete();
            
            $table->date('maintained_at')->comment('整備日');
            $table->unsignedInteger('odometer')->nullable()->comment('整備時の総走行距離');
            $table->string('title')->comment('整備内容（オイル交換など）');
            $table->unsignedInteger('cost')->nullable()->comment('費用(円)');
            $table->text('note')->nullable()->comment('詳細メモ');
            
            $table->timestamps();
            
            $table->index(['my_bike_id', 'maintained_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('my_bikes');
    }
};