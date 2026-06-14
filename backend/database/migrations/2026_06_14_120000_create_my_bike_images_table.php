<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('my_bike_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('my_bike_id')->constrained()->cascadeOnDelete();
            // 非公開ディスク上の保存パス（owner-checkの配信ルート経由でのみ返す）
            $table->string('path');
            $table->string('caption', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['my_bike_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_bike_images');
    }
};
