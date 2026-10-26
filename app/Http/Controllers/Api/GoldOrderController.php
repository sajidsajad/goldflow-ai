<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoldPrice;
use App\Services\GoldOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GoldOrderController extends Controller
{
    public function store(
        Request $request,
        GoldOrderService $goldOrderService
    ): JsonResponse {
        // Validate the API contract before calling business logic.
        $validated = Validator::make($request->all(), [
            'gold_price_id' => ['required', 'integer', 'min:1'],
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
            ],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        // Verify the selected price exists. The service also checks this
        // defensively because business rules must not depend on the controller.
        $goldPriceExists = GoldPrice::query()
            ->whereKey($validated['gold_price_id'])
            ->exists();

        if (! $goldPriceExists) {
            throw ValidationException::withMessages([
                'gold_price_id' => 'The selected gold price does not exist.',
            ]);
        }

        // Never accept user_id from the request; use the authenticated user.
        $order = $goldOrderService->create(
            $request->user(),
            (string) $validated['gold_price_id'],
            $validated['amount'],
            $validated['idempotency_key']
        );

        // Return 200 for an existing idempotent result and 201 for a new order.
        $status = $order->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => [
                'id' => $order->id,
                'gold_price_id' => $order->gold_price_id,
                'amount' => $order->amount,
                'price_per_gram' => $order->price_per_gram,
                'grams' => $order->grams,
                'currency' => $order->currency,
                'status' => $order->status,
            ],
        ], $status);
    }
}
