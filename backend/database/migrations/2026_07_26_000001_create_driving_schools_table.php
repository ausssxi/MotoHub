<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 二輪教習に対応した指定自動車教習所の一覧。
 *
 * 方針:
 *  - 一次情報は各都道府県の指定自動車教習所協会が公表する会員校リスト（普自二/大自二の○列付き）。
 *  - source_url に必ずその出典URLを入れる。
 *  - verified_at が入っている行だけを公開対象とする（人手確認ゲート）。NULL の行は表示しない。
 *  - 料金は各校サイトで個別公表のためこのテーブルには持たない（後段で別途）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_schools', function (Blueprint $table) {
            $table->id();
            $table->string('prefecture', 20);               // 神奈川県
            $table->string('prefecture_slug', 20);          // kanagawa
            $table->string('city', 60);                     // 横浜市鶴見区
            $table->string('name', 120);                    // 新鶴見ドライビングスクール
            $table->string('official_url', 255)->nullable();
            $table->boolean('futsuu_nirin')->default(false); // 普通二輪
            $table->boolean('oogata_nirin')->default(false); // 大型二輪
            $table->string('source_url', 255);               // 出典（協会公式リスト等）
            $table->date('verified_at')->nullable();         // 非NULL = 人手確認済み = 公開
            $table->timestamps();

            $table->unique(['prefecture_slug', 'name'], 'driving_schools_pref_name_unique');
            $table->index(['prefecture_slug', 'city'], 'driving_schools_pref_city_idx');
            $table->index('verified_at', 'driving_schools_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_schools');
    }
};
