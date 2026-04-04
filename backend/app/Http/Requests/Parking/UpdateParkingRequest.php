<?php

declare(strict_types=1);

namespace App\Http\Requests\Parking;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateParkingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parking = $this->route('bikeParking');
        $user = $this->user();

        return $user && ($user->is_admin || $parking->user_id === $user->id);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'prefecture' => ['required', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:50'],
            'parking_type' => ['nullable', 'in:bike_only,car_shared,bicycle_shared,other'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'price_per_hour' => ['nullable', 'integer', 'min:0'],
            'price_per_day' => ['nullable', 'integer', 'min:0'],
            'price_per_month' => ['nullable', 'integer', 'min:0'],
            'price_detail' => ['nullable', 'string', 'max:500'],
            'is_free' => ['nullable', 'boolean'],
            'is_covered' => ['nullable', 'boolean'],
            'is_locked' => ['nullable', 'boolean'],
            'has_security_camera' => ['nullable', 'boolean'],
            'available_24h' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delete_images' => ['nullable', 'array'],
            'delete_images.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '駐車場名を入力してください。',
            'address.required' => '住所を入力してください。',
            'latitude.required' => '地図上で位置を指定してください。',
            'longitude.required' => '地図上で位置を指定してください。',
            'prefecture.required' => '都道府県を入力してください。',
        ];
    }
}
