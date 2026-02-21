<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. タグ自体のマスターテーブル
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('タグ名（例: ETC）');
            $table->string('slug')->unique()->nullable()->comment('URL用（例: etc）');
            $table->timestamps();
        });

        // 2. 車両(listings)とタグの中間テーブル
        Schema::create('listing_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            
            // 同じ車両に同じタグが重複して登録されないようにする
            $table->unique(['listing_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_tag');
        Schema::dropIfExists('tags');
    }
};