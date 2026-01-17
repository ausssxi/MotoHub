<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * サイト（情報ソース）の初期データを投入するシーダー
 */
class SiteSeeder extends Seeder
{
    /**
     * データベースへのデータ投入を実行
     */
    public function run(): void
    {
        // GooBike
        DB::table('sites')->updateOrInsert(
            ['name' => 'GooBike'],
            [
                'base_url' => 'https://www.goobike.com'
            ]
        );

        // BDSバイクセンサー
        DB::table('sites')->updateOrInsert(
            ['name' => 'BDS'],
            [
                'base_url' => 'https://www.bds-bikesensor.net'
            ]
        );

        // Webike
        DB::table('sites')->updateOrInsert(
            ['name' => 'Webike'],
            [
                'base_url' => 'https://moto.webike.net'
            ]
        );
    }
}