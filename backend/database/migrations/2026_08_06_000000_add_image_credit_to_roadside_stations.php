<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * roadside_stations に画像クレジット（著者・ライセンス・ライセンスURL）3列を追加する。
 *
 * image_url は Wikimedia Commons の画像を指すことが多く、Commons のライセンス
 * （CC BY-SA 等）は「著者名＋ライセンス表記」の掲示が必須。表示に必要な最小限の
 * メタデータをここに保持する（取得は roadside:fetch-image-credits コマンド）。
 * すべて nullable（未取得・非Commons画像では NULL のまま）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadside_stations', function (Blueprint $table) {
            $table->string('image_author', 255)->nullable()->after('image_url')->comment('画像の著者（Commons Artist）');
            $table->string('image_license', 100)->nullable()->after('image_author')->comment('画像ライセンス短縮名（例: CC BY-SA 4.0）');
            $table->string('image_license_url', 255)->nullable()->after('image_license')->comment('画像ライセンスのURL');
        });
    }

    public function down(): void
    {
        Schema::table('roadside_stations', function (Blueprint $table) {
            $table->dropColumn(['image_author', 'image_license', 'image_license_url']);
        });
    }
};
