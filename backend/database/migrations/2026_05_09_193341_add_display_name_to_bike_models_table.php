<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->string('display_name', 255)->nullable()->after('name')->comment('表示用の正式名称（日本語）');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
