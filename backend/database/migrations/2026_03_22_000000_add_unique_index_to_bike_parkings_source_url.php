<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->unique('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
        });
    }
};
