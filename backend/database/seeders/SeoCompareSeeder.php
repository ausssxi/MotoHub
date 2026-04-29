<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BikeModel;
use App\Models\SeoCompare;
use Illuminate\Database\Seeder;

class SeoCompareSeeder extends Seeder
{
    public function run(): void
    {
        // 人気の比較ペア30組: [model1_name, model2_name, sort_order]
        $pairs = [
            ['レブル250', 'GB350', 10],
            ['PCX', 'NMAX', 20],
            ['Ninja250', 'CBR250RR', 30],
            ['YZF-R25', 'CBR250RR', 40],
            ['Ninja400', 'CB400SF', 50],
            ['MT-07', 'SV650', 60],
            ['Z900RS', 'CB1100', 70],
            ['モンキー125', 'グロム', 80],
            ['スーパーカブ110', 'クロスカブ110', 90],
            ['CT125ハンターカブ', 'クロスカブ110', 100],
            ['GB350', 'SR400', 110],
            ['レブル250', 'Vストローム250', 120],
            ['PCX', 'アドレス125', 130],
            ['Ninja250', 'YZF-R25', 140],
            ['Ninja400', 'YZF-R3', 150],
            ['MT-09', 'Z900', 160],
            ['CBR650R', 'Ninja650', 170],
            ['アフリカツイン', 'Vストローム1050', 180],
            ['セロー250', 'CRF250L', 190],
            ['GSX250R', 'Ninja250', 200],
            ['ジクサー150', 'PCX160', 210],
            ['MT-25', 'Z250', 220],
            ['YZF-R7', 'CBR650R', 230],
            ['CB250R', 'MT-25', 240],
            ['レブル500', 'レブル250', 250],
            ['ZX-25R', 'CBR250RR', 260],
            ['CB1300SF', 'ZRX1200', 270],
            ['Ninja ZX-6R', 'CBR600RR', 280],
            ['ハヤブサ', 'Ninja ZX-14R', 290],
            ['TMAX', 'X-MAX', 300],
        ];

        $notFound = [];

        foreach ($pairs as [$name1, $name2, $sortOrder]) {
            $m1 = BikeModel::where('name', $name1)->first();
            $m2 = BikeModel::where('name', $name2)->first();

            if (!$m1 || !$m2) {
                if (!$m1) $notFound[] = $name1;
                if (!$m2) $notFound[] = $name2;
                continue;
            }

            $slug1 = $m1->slug ?? (string) $m1->id;
            $slug2 = $m2->slug ?? (string) $m2->id;
            $slug = "{$slug1}-vs-{$slug2}";

            SeoCompare::updateOrCreate(
                ['slug' => $slug],
                [
                    'model1_id' => $m1->id,
                    'model2_id' => $m2->id,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        if (!empty($notFound)) {
            $this->command->warn('以下の車種名が見つかりませんでした: ' . implode(', ', array_unique($notFound)));
        }

        $this->command->info('SeoCompareSeeder: ' . SeoCompare::count() . '件の比較ペアを登録しました。');
    }
}
