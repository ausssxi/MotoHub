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
            $table->string('tel', 30)->nullable()->after('address');
            $table->string('parking_form', 50)->nullable()->after('parking_type');
            $table->string('closed_days', 100)->nullable()->after('parking_form');
            $table->string('available_hours', 100)->nullable()->after('closed_days');
            $table->text('price_detail')->nullable()->after('price_per_month');
            $table->string('vehicle_restriction', 200)->nullable()->after('price_detail');
            $table->text('notes')->nullable()->after('vehicle_restriction');
            $table->string('management_company', 200)->nullable()->after('notes');
            $table->date('jmpsa_updated_at')->nullable()->after('management_company');
        });
    }

    public function down(): void
    {
        Schema::table('bike_parkings', function (Blueprint $table) {
            $table->dropColumn([
                'tel',
                'parking_form',
                'closed_days',
                'available_hours',
                'price_detail',
                'vehicle_restriction',
                'notes',
                'management_company',
                'jmpsa_updated_at',
            ]);
        });
    }
};
