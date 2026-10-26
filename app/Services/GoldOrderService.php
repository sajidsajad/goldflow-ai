<?php

namespace App\Services;

use App\Models\GoldOrder;
use App\Models\GoldPrice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoldOrderService
{
    public function create(
        User $user,
        string $goldPriceId,
        string $amount,
        string $idempotencyKey
    ): GoldOrder {
        // Validate and normalize the amount before any idempotency comparison.
        $amount = $this->normalizeAmount($amount);

        try {
            return DB::transaction(function () use (
                $user,
                $goldPriceId,
                $amount,
                $idempotencyKey
            ): GoldOrder {
                // A key identifies one logical request for this user.
                $existingOrder = GoldOrder::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder !== null) {
                    return $this->returnMatchingOrder(
                        $existingOrder,
                        $goldPriceId,
                        $amount
                    );
                }

                // Lock the selected price row while reading its snapshot.
                $goldPrice = GoldPrice::query()
                    ->whereKey($goldPriceId)
                    ->lockForUpdate()
                    ->first();

                if ($goldPrice === null) {
                    throw ValidationException::withMessages([
                        'gold_price_id' => 'The selected gold price does not exist.',
                    ]);
                }

                $pricePerGram = (string) $goldPrice->price_per_gram;

                if (
                    ! preg_match('/^\d{1,11}\.\d{4}$/', $pricePerGram)
                    || bccomp($pricePerGram, '0.0000', 4) <= 0
                ) {
                    throw ValidationException::withMessages([
                        'gold_price_id' => 'The selected gold price is invalid.',
                    ]);
                }

                // Use integer paise for the amount and price calculations.
                $amountPaise = bcmul($amount, '100', 0);
                $pricePaisePerGram = bcmul($pricePerGram, '100', 0);

                $grams = bcdiv(
                    $amountPaise,
                    $pricePaisePerGram,
                    8
                );

                return GoldOrder::create([
                    'user_id' => $user->id,
                    'gold_price_id' => $goldPrice->id,
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'price_per_gram' => $pricePerGram,
                    'grams' => $grams,
                    'currency' => $goldPrice->currency,
                    'status' => 'pending',
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            // MySQL error 1062 means a unique constraint was violated.
            $mysqlErrorCode = (int) ($exception->errorInfo[1] ?? 0);

            if ($mysqlErrorCode !== 1062) {
                throw $exception;
            }

            // Another request may have created this order concurrently.
            $existingOrder = GoldOrder::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder !== null) {
                return $this->returnMatchingOrder(
                    $existingOrder,
                    $goldPriceId,
                    $amount
                );
            }

            // The violated unique constraint was unrelated to this key.
            throw $exception;
        }
    }

    /**
     * Validate that a repeated idempotency key represents the same request.
     */
    private function returnMatchingOrder(
        GoldOrder $existingOrder,
        string $goldPriceId,
        string $amount
    ): GoldOrder {
        if (
            (string) $existingOrder->gold_price_id !== $goldPriceId
            || bccomp((string) $existingOrder->amount, $amount, 2) !== 0
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key has already been used with different purchase details.',
            ]);
        }

        return $existingOrder;
    }

    /**
     * Normalize a positive monetary amount to two decimal places.
     */
    private function normalizeAmount(string $amount): string
    {
        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be a positive number with at most two decimal places.',
            ]);
        }

        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        return $amount;
    }
}
