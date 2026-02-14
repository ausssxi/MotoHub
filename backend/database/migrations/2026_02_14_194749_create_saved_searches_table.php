<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();
            
            // 条件に名前をつける（例: "PCX 20万以下"）
            // 指定がない場合は自動生成するロジックにします
            $table->string('name')->nullable();

            // 検索パラメータをJSONで保存 (例: {"manufacturer_id": 1, "max_price": 20})
            $table->json('conditions');
            
            // 通知設定
            $table->boolean('is_active')->default(true);
            $table->boolean('mail_notify')->default(true);
            $table->boolean('line_notify')->default(false); // 将来用

            // 重複通知防止用
            $table->timestamp('last_notified_at')->nullable();

            $table->timestamps();
            
            // 1ユーザーあたりの保存数制限などに使うインデックス
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};