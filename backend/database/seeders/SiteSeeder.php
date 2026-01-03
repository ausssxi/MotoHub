<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 初期データの投入
        DB::table('sites')->updateOrInsert(
            ['name' => 'GooBike'],
            [
                'base_url' => 'https://www.goobike.com'
            ]
        );

        DB::table('sites')->updateOrInsert(
            ['name' => 'BDS'],
            [
                'base_url' => 'https://www.bds-bikesensor.net'
            ]
        );
    }
}