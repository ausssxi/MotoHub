<?php

declare(strict_types=1);

namespace App\Http\Requests\Bike;

use Illuminate\Foundation\Http\FormRequest;

class BikeSearchRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを行う権限があるか
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            // 基本検索
            'keyword'            => ['nullable', 'string', 'max:100'],
            'prefecture'         => ['nullable', 'string', 'max:20'],
            'sort'               => ['nullable', 'string', 'in:bargain_desc,latest,price_asc,price_desc,mileage_asc,mileage_desc,year_desc,year_asc'],
            'count_only'         => ['nullable', 'boolean'],
            
            // IDリレーション
            'manufacturer_id'    => ['nullable', 'integer', 'exists:manufacturers,id'],
            'bike_model_id'      => ['nullable', 'integer', 'exists:bike_models,id'],
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            
            // 価格（万円）
            'min_price'          => ['nullable', 'numeric', 'min:0'],
            'max_price'          => ['nullable', 'numeric', 'min:0'],
            
            // 走行距離（km）
            'min_mileage'        => ['nullable', 'integer', 'min:0'],
            'max_mileage'        => ['nullable', 'integer', 'min:0'],
            
            // 年式
            'min_year'           => ['nullable', 'integer', 'min:1900'],
            'max_year'           => ['nullable', 'integer', 'min:1900'],
            
            // 排気量（cc）
            'min_displacement'   => ['nullable', 'integer', 'min:0'],
            'max_displacement'   => ['nullable', 'integer', 'min:0'],
            
            // 状態・フラグ系 (0/1 または true/false)
            'is_new'             => ['nullable', 'in:0,1,true,false'],
            'has_repair_history' => ['nullable', 'in:0,1,true,false'],
            
            // タグ
            'tag'                => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Serviceに渡すためのクリーンなフィルタ配列を生成して返す
     * ※ バリデーションを通過した（安全な）データのみを抽出
     */
    public function toFilters(): array
    {
        // $this->validated() を使うことで、rules() に定義されていない不正なパラメータを完全に排除
        $validated = $this->validated();

        $filterKeys = [
            'min_price', 'max_price', 
            'min_mileage', 'max_mileage',
            'min_year', 'max_year', 
            'min_displacement', 'max_displacement',
            'manufacturer_id', 'bike_model_id', 'category_id',
            'is_new', 'has_repair_history', 'tag'
        ];

        $filters = [];
        foreach ($filterKeys as $key) {
            if (isset($validated[$key])) {
                $filters[$key] = $validated[$key];
            }
        }

        return $filters;
    }
}