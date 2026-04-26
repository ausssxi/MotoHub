<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_features', function (Blueprint $table) {
            $table->text('guide_content')->nullable()->after('content_header');
        });
    }

    public function down(): void
    {
        Schema::table('seo_features', function (Blueprint $table) {
            $table->dropColumn('guide_content');
        });
    }
};
