<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 返信への「ナイス（参考になった）」投票。voter_hash=ログインはuser由来・ゲストはip_hash由来で重複防止。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_reply_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_reply_id')->constrained('discussion_replies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voter_hash', 64); // sha256(user:{id}) or ip_hash
            $table->timestamps();

            $table->unique(['discussion_reply_id', 'voter_hash'], 'reply_voter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reply_votes');
    }
};
