<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 教習所の二輪コース（区分×MT/AT×所持免許）ごとの料金。
 *
 * 行の存在＝その区分を開講している、という意味づけ。開講しているが料金非公表なら price_yen=NULL。
 * 料金は各校が個別に改定するため、行ごとに verified_at（確認日）と source_url（各校の公式料金ページ）を持つ。
 *
 * 各カラムの取りうる値:
 *  - vehicle_class（教習車種）:
 *      kogata_nirin  … 小型限定普通二輪（〜125cc）
 *      futsuu_nirin  … 普通二輪（〜400cc）
 *      oogata_nirin  … 大型二輪（無制限）
 *  - transmission（変速）:
 *      mt … マニュアル
 *      at … オートマチック（AT限定）
 *  - prerequisite（前提となる所持免許）:
 *      none            … 免許なし、または原付のみ
 *      car             … 普通自動車免許以上を所持
 *      kogata_nirin    … 小型限定普通二輪を所持
 *      futsuu_nirin_at … 普通二輪AT限定を所持
 *      futsuu_nirin_mt … 普通二輪MTを所持
 *  - enrollment_type（通学形態）:
 *      commute … 通学（今回はこれのみ使用）
 *      camp    … 合宿（将来対応。再マイグレーションを避けるため先に用意）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_school_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driving_school_id')            // driving_schools.id への外部キー
                ->constrained('driving_schools')
                ->cascadeOnDelete();                          // 校が消えたらコースも消す
            $table->string('vehicle_class', 20);              // kogata_nirin / futsuu_nirin / oogata_nirin
            $table->string('transmission', 2);                // mt / at
            $table->string('prerequisite', 20);               // none / car / kogata_nirin / futsuu_nirin_at / futsuu_nirin_mt
            $table->string('enrollment_type', 10)->default('commute'); // commute / camp
            $table->unsignedInteger('price_yen')->nullable();  // 通常期・税込・総額（入所〜卒業一式）。非公表なら NULL
            $table->string('price_note', 255)->nullable();     // 繁忙期料金・学割・条件付き等の但し書き
            $table->string('source_url', 255);                 // その料金を載せた各校の公式料金ページURL
            $table->date('verified_at')->nullable();           // 料金の確認日（改定されるため行ごとに持つ）
            $table->timestamps();

            $table->unique(
                ['driving_school_id', 'vehicle_class', 'transmission', 'prerequisite', 'enrollment_type'],
                'driving_school_courses_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_school_courses');
    }
};
