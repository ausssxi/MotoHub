<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('needs_reindex')->default(false)->after('is_sold_out');
            $table->index('needs_reindex');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['needs_reindex']);
            $table->dropColumn('needs_reindex');
        });
    }
};
