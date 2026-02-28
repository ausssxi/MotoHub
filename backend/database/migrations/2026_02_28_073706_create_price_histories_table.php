<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->onDelete('cascade');
            $table->integer('old_price')->comment('変更前の価格(円)');
            $table->integer('new_price')->comment('変更後の価格(円)');
            $table->boolean('is_notified')->default(false)->comment('通知済みフラグ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};