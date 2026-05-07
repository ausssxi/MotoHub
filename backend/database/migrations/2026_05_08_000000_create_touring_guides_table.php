<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('touring_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->text('excerpt')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->tinyInteger('zoom_level')->default(12);
            $table->string('prefecture', 10);
            $table->enum('difficulty', ['初級', '中級', '上級']);
            $table->smallInteger('distance_km')->nullable();
            $table->string('duration_text', 50)->nullable();
            $table->string('best_season', 100)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->smallInteger('reading_time_minutes')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('prefecture');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('touring_guides');
    }
};
