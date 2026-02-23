<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use App\Models\BikeModel;
use App\Models\Manufacturer;

/**
 * 検索キーワードから「メーカー」「車種」「地域」「タグ」を自動推論し、
 * 自然言語を構造化されたフィルター条件に変換する高度なエンジン
 */
final class KeywordInferrer
{
    public function infer(string $keyword): array
    {
        $result = [
            'manufacturer_id'   => null,
            'bike_model_id'     => null,
            'prefecture'        => null,
            'tag'               => null,
            'remaining_keyword' => null, // 推論できなかった残りのキーワード
        ];

        if (empty(trim($keyword))) {
            return $result;
        }

        // 1. キーワードをスペース（全角・半角）で分割して配列にする
        $words = preg_split('/[\s　]+/', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);
        $unmatchedWords = [];

        // 判定用の辞書データを準備
        $prefectures = collect(config('bike.regions', []))->flatten()->toArray();
        $synonyms = config('bike_synonyms', []);
        $tagDictionary = config('tags.dictionary', []);

        // 2. 分割した単語を1つずつ解析する
        foreach ($words as $word) {
            $normalizedWord = mb_strtolower($word);
            $matched = false;

            // 同義語展開（この単語が辞書のどの言葉に該当するか広げる）
            $searchTargets = [$normalizedWord];
            foreach ($synonyms as $key => $syns) {
                if ($normalizedWord === mb_strtolower($key) || in_array($normalizedWord, array_map('mb_strtolower', $syns))) {
                    $searchTargets[] = mb_strtolower($key);
                    $searchTargets = array_merge($searchTargets, array_map('mb_strtolower', $syns));
                }
            }
            $searchTargets = array_unique($searchTargets);

            // A. 地域（都道府県）の判定
            if (!$result['prefecture']) {
                foreach ($prefectures as $pref) {
                    // 「東京」でも「東京都」でもマッチさせる
                    if ($word === $pref || $word === str_replace(['都','府','県'], '', $pref)) {
                        $result['prefecture'] = $pref;
                        $matched = true;
                        break;
                    }
                }
            }

            // B. 車種名の判定
            if (!$matched && !$result['bike_model_id']) {
                foreach ($searchTargets as $target) {
                    $bikeModel = BikeModel::query()
                        ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', ''), '　', '')) = ?", [str_replace([' ', '　'], '', $target)])
                        ->first();

                    if ($bikeModel) {
                        $result['bike_model_id'] = $bikeModel->id;
                        $result['manufacturer_id'] = $bikeModel->manufacturer_id;
                        $matched = true;
                        break;
                    }
                }
            }

            // C. メーカー名の判定
            if (!$matched && !$result['manufacturer_id']) {
                foreach ($searchTargets as $target) {
                    $manufacturer = Manufacturer::query()
                        ->whereRaw("LOWER(REPLACE(REPLACE(name, ' ', ''), '　', '')) = ?", [str_replace([' ', '　'], '', $target)])
                        ->first();

                    if ($manufacturer) {
                        $result['manufacturer_id'] = $manufacturer->id;
                        $matched = true;
                        break;
                    }
                }
            }

            // D. タグ（装備・状態・カスタム）の判定
            if (!$matched && !$result['tag']) {
                foreach ($tagDictionary as $dictKey => $tagValue) {
                    if (mb_stripos($normalizedWord, mb_strtolower($dictKey)) !== false) {
                        $result['tag'] = $tagValue;
                        $matched = true;
                        break;
                    }
                }
            }

            // E. どれにも当てはまらなかった言葉は「純粋なキーワード」として残す
            if (!$matched) {
                $unmatchedWords[] = $word;
            }
        }

        // 3. 残った言葉を再度スペースで繋いで検索エンジンに渡す
        $result['remaining_keyword'] = empty($unmatchedWords) ? null : implode(' ', $unmatchedWords);

        return $result;
    }
}