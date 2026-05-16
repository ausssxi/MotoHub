<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('touring_spots', function (Blueprint $table) {
            $table->id();
            $table->string('prefecture', 10);
            $table->string('slug')->unique();
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('image_url')->nullable();
            $table->json('highlights')->nullable();
            $table->string('recommended_season', 50)->nullable();
            $table->string('difficulty', 20)->nullable();
            $table->unsignedSmallInteger('distance_km')->nullable();
            $table->timestamps();

            $table->index('prefecture');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('touring_spots');
    }
};
