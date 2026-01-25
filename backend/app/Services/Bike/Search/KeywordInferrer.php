<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use Illuminate\Support\Str;

/**
 * 検索キーワードからメーカーや車種を推論するクラス
 */
final class KeywordInferrer
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo
    ) {}

    /**
     * キーワード推論の実行
     */
    public function infer(string $keyword): array
    {
        $res = ['manufacturer_id' => null, 'bike_model_id' => null];
        if (mb_strlen($keyword) < 2) return $res;

        $normalizedKeyword = $this->normalizeString($keyword);

        // 1. ブランド名単体での検索かチェック
        $allManufacturers = $this->manufacturerRepo->getAll();
        $matchedManufacturer = $allManufacturers->first(
            fn($m) => $this->normalizeString($m->name) === $normalizedKeyword
        );

        if ($matchedManufacturer) {
            $res['manufacturer_id'] = (int)$matchedManufacturer->id;
            return $res;
        }

        // 2. 車種検索
        $matchedModels = $this->modelRepo->searchByName($keyword, 30);
        if ($matchedModels->isEmpty()) return $res;

        $mIds = $matchedModels->pluck('manufacturer_id')->unique();
        if ($mIds->count() === 1) {
            $res['manufacturer_id'] = (int)$mIds->first();
        }

        $exactMatch = $matchedModels->first(
            fn($m) => $this->normalizeString($m->name) === $normalizedKeyword
        );

        if ($exactMatch) {
            if (!str_contains($exactMatch->name, 'その他')) {
                $res['bike_model_id'] = (int)$exactMatch->id;
                $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
            } else {
                $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
            }
        } elseif ($matchedModels->count() === 1) {
            $model = $matchedModels->first();
            if (!str_contains($model->name, 'その他')) {
                $res['bike_model_id'] = (int)$model->id;
            }
        }

        return $res;
    }

    private function normalizeString(string $str): string
    {
        return Str::lower(str_replace([' ', '　'], '', mb_convert_kana($str, "asKV")));
    }
}