<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_parking_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_parking_id')->constrained('bike_parkings')->cascadeOnDelete();
            $table->string('image_path');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('bike_parking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_parking_images');
    }
};
