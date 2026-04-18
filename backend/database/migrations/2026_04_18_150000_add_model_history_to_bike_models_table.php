<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->json('model_history')->nullable()->after('enriched_content');
            $table->timestamp('history_generated_at')->nullable()->after('model_history');
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $table->dropColumn(['model_history', 'history_generated_at']);
        });
    }
};
