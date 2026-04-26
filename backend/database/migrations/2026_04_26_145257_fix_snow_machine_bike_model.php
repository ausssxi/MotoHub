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
        DB::table('bike_models')
            ->where('id', 1536)
            ->update(['displacement' => 0, 'category' => 'その他']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bike_models')
            ->where('id', 1536)
            ->update(['displacement' => null, 'category' => '不明']);
    }
};
