<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('サイト名 (GooBike, BDS等)');
            $table->string('base_url', 255)->nullable()->comment('サイトのトップURL');
            $table->timestamp('created_at')->nullable()->comment('作成日時');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};