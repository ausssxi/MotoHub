<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint'      => 'required|string',
            'p256dh'        => 'required|string',
            'auth'          => 'required|string',
            'bike_model_id' => 'nullable|integer|exists:bike_models,id',
        ]);

        $endpointHash = hash('sha256', $validated['endpoint']);

        PushSubscription::updateOrCreate(
            [
                'endpoint_hash'  => $endpointHash,
                'bike_model_id'  => $validated['bike_model_id'] ?? null,
            ],
            [
                'endpoint' => $validated['endpoint'],
                'p256dh'   => $validated['p256dh'],
                'auth'     => $validated['auth'],
                'user_id'  => auth()->id(),
            ]
        );

        return response()->json(['message' => '通知登録完了'], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint'      => 'required|string',
            'bike_model_id' => 'nullable|integer',
        ]);

        $endpointHash = hash('sha256', $validated['endpoint']);

        PushSubscription::where('endpoint_hash', $endpointHash)
            ->where('bike_model_id', $validated['bike_model_id'] ?? null)
            ->delete();

        return response()->json(['message' => '通知解除完了']);
    }

    public function subscribedModels(Request $request): JsonResponse
    {
        $ids = array_filter(explode(',', $request->query('ids', '')));
        if (empty($ids)) {
            return response()->json([]);
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'name' => $m->manufacturer?->name . ' ' . $m->name,
                'url'  => $m->seo_url ?: '/bikes/search?bike_model_id=' . $m->id,
            ]);

        return response()->json($models);
    }
}
