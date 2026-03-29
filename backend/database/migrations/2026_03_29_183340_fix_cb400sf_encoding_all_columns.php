<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CB400SF NC42 系 3車種の EUC-JP→UTF-8 変換ミスによる文字化けを修正。
     * manufacturer_id はサブクエリで取得し、車種名で対象を特定する。
     */
    public function up(): void
    {
        $hondaId = DB::table('manufacturers')->where('name', 'ホンダ')->value('id');

        if (!$hondaId) {
            return;
        }

        $correct = [
            'model_code'       => 'ホンダ・2BL-NC42',
            'engine_type'      => '水冷4ストローク・4気筒DOHC4バルブ',
            'fuel_supply'      => 'インジェクション',
            'max_power'        => '41(56PS)／11000',
            'max_torque'       => '39(4.0kg･m)／9500',
            'tire_size_front'  => '120/60ZR17M/C（55W）',
            'tire_size_rear'   => '160/60ZR17M/C（69W）',
            'brake_type_front' => '油圧式ダブルディスク',
            'brake_type_rear'  => '油圧式ディスク',
        ];

        $targetNames = [
            'cb400super four vtec revo',
            'cb400super fourバージョンr',
            'cb400super ボルドール vtec revo',
        ];

        DB::table('bike_models')
            ->where('manufacturer_id', $hondaId)
            ->whereIn('name', $targetNames)
            ->update($correct);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 文字化けデータに戻す必要はないため空
    }
};
