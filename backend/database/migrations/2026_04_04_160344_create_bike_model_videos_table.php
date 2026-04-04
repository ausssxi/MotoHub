<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_model_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_model_id')->constrained('bike_models')->cascadeOnDelete();
            $table->string('video_id', 20);
            $table->string('title');
            $table->string('thumbnail_url')->default('');
            $table->string('channel_name')->default('');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bike_model_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_model_videos');
    }
};
