<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->longText('body');
            $table->text('excerpt')->nullable();
            $table->string('eyecatch_image', 500)->nullable();
            $table->foreignId('series_id')->nullable()->constrained('blog_series')->nullOnDelete();
            $table->unsignedSmallInteger('series_order')->nullable();
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->default(1);
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['series_id', 'series_order']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
