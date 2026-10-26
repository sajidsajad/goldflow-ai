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
        try {
            return DB::transaction(function () use (
                $user,
                $goldPriceId,
                $amount,
                $idempotencyKey
            ): GoldOrder {
                $existingOrder = GoldOrder::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder !== null) {
                    return $existingOrder;
                }

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
            // MySQL error 1062: duplicate entry for a unique index.
            $mysqlErrorCode = (int) ($exception->errorInfo[1] ?? 0);

            if ($mysqlErrorCode !== 1062) {
                throw $exception;
            }

            $existingOrder = GoldOrder::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder !== null) {
                return $existingOrder;
            }

            // A different unique constraint may have failed.
            throw $exception;
        }
    }
}
