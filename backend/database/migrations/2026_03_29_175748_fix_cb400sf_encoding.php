<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 次のマイグレーション (fix_cb400sf_encoding_all_columns) で全カラムを修正するため、
     * このマイグレーションは空にしています（既に実行済みのため削除不可）。
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
