<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('old_slug', 150)->nullable()->after('slug');
            $table->index('old_slug');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropIndex(['old_slug']);
            $table->dropColumn('old_slug');
        });
    }
};
