<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->json('enriched_content')->nullable()->after('brake_type_rear');
            $table->timestamp('content_generated_at')->nullable()->after('enriched_content');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropColumn(['enriched_content', 'content_generated_at']);
        });
    }
};
