<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('is_active')
                ->constrained('stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
