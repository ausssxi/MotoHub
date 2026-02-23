<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use App\Models\BikeModel;
use App\Models\Manufacturer;

/**
 * 検索キーワードから「メーカー」や「車種」を自動推論するクラス
 */
final class KeywordInferrer
{
    public function infer(string $keyword): array
    {
        $result = [
            'manufacturer_id' => null,
            'bike_model_id'   => null,
        ];

        if (empty(trim($keyword))) {
            return $result;
        }

        // 検索時の表記揺れを吸収（小文字化、スペース除去）
        $normalizedKeyword = mb_strtolower(str_replace([' ', '　'], '', $keyword));

        // 1. 同義語辞書（config/bike_synonyms.php）をチェックして候補を広げる
        $synonyms = config('bike_synonyms', []);
        $searchTargets = [$normalizedKeyword]; 
        
        foreach ($synonyms as $key => $words) {
            if ($normalizedKeyword === mb_strtolower($key) || in_array($normalizedKeyword, array_map('mb_strtolower', $words))) {
                $searchTargets[] = mb_strtolower($key); 
                $searchTargets = array_merge($searchTargets, array_map('mb_strtolower', $words));
            }
        }

        // 2. 車種名の推論（DBと照合）
        foreach (array_unique($searchTargets) as $target) {
            $bikeModel = BikeModel::query()
                ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', ''), '　', '')) = ?", [$target])
                ->first();

            if ($bikeModel) {
                $result['bike_model_id'] = $bikeModel->id;
                $result['manufacturer_id'] = $bikeModel->manufacturer_id;
                return $result; // 見つかったら即終了
            }
        }

        // 3. メーカー名の推論 (車種が特定できなかった場合)
        foreach (array_unique($searchTargets) as $target) {
            $manufacturer = Manufacturer::query()
                ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', ''), '　', '')) = ?", [$target])
                ->first();

            if ($manufacturer) {
                $result['manufacturer_id'] = $manufacturer->id;
                return $result;
            }
        }

        return $result;
    }
}