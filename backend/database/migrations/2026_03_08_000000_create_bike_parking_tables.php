<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_parkings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('prefecture', 10)->nullable();
            $table->string('city', 50)->nullable();

            $table->enum('parking_type', ['bike_only', 'car_shared', 'bicycle_shared', 'other'])->default('bike_only');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('price_per_hour')->nullable();
            $table->unsignedInteger('price_per_day')->nullable();
            $table->unsignedInteger('price_per_month')->nullable();

            $table->boolean('is_free')->default(false);
            $table->boolean('is_covered')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->boolean('has_security_camera')->default(false);
            $table->boolean('available_24h')->default(false);

            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('source_url')->nullable();

            $table->decimal('avg_rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('prefecture');
            $table->index(['latitude', 'longitude']);
            $table->index('parking_type');
        });

        Schema::create('parking_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_parking_id')->constrained('bike_parkings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nickname', 50);
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->date('visited_at')->nullable();
            $table->timestamps();

            $table->index('bike_parking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_reviews');
        Schema::dropIfExists('bike_parkings');
    }
};
