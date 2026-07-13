<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レビューへの「参考になった」投票（thread_reply_votes と同型）。voter_hash で重複防止。
 * reviews.helpful_count は表示・ソート用の集計キャッシュ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voter_hash', 64); // sha256(user:{id}) or ip_hash
            $table->timestamps();

            $table->unique(['review_id', 'voter_hash'], 'review_voter_unique');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0)->after('rating_cost_performance');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('helpful_count');
        });
        Schema::dropIfExists('review_helpful_votes');
    }
};
