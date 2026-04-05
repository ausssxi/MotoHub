<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bike_news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url')->unique();
            $table->string('source')->default('');
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('bike_model_id')->nullable()->constrained('bike_models')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('picks_count')->default(0);
            $table->timestamps();

            $table->index('published_at');
            $table->index('manufacturer_id');
            $table->index('bike_model_id');
        });

        Schema::create('news_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained('bike_news')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->index('news_id');
        });

        Schema::create('news_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained('bike_news')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['news_id', 'user_id']);
        });

        Schema::create('news_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('news_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_comment_likes');
        Schema::dropIfExists('news_picks');
        Schema::dropIfExists('news_comments');
        Schema::dropIfExists('bike_news');
    }
};
