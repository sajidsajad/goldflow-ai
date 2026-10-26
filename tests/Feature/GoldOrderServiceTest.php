<?php

namespace Tests\Feature;

use App\Models\GoldOrder;
use App\Models\GoldPrice;
use App\Models\User;
use App\Services\GoldOrderService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoldOrderServiceTest extends TestCase
{
    private function createGoldPrice(): GoldPrice
    {
        return GoldPrice::create([
            'price_per_gram' => '10250.0000',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);
    }

    public function test_it_creates_an_order_using_a_gold_price_snapshot(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();

        $order = app(GoldOrderService::class)->create(
            $user,
            (string) $goldPrice->id,
            '5000.00',
            'order-key-001'
        );

        $this->assertSame('5000.00', $order->amount);
        $this->assertSame('10250.0000', $order->price_per_gram);
        $this->assertSame('0.48780487', $order->grams);
        $this->assertSame('pending', $order->status);

        $goldPrice->update(['price_per_gram' => '11000.0000']);

        $this->assertSame(
            '10250.0000',
            $order->fresh()->price_per_gram
        );
    }

    public function test_it_returns_the_existing_order_for_a_repeated_idempotency_key(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $service = app(GoldOrderService::class);

        $firstOrder = $service->create(
            $user,
            (string) $goldPrice->id,
            '5000.00',
            'order-key-002'
        );

        $secondOrder = $service->create(
            $user,
            (string) $goldPrice->id,
            '5000.00',
            'order-key-002'
        );

        $this->assertSame($firstOrder->id, $secondOrder->id);

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $user->id)
                ->where('idempotency_key', 'order-key-002')
                ->count()
        );
    }

    public function test_it_rejects_a_nonexistent_gold_price_without_creating_an_order(): void
    {
        $user = User::factory()->create();
        $service = app(GoldOrderService::class);

        try {
            $service->create(
                $user,
                '999999',
                '5000.00',
                'order-key-003'
            );

            $this->fail('Expected a validation exception for a nonexistent gold price.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'gold_price_id',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            GoldOrder::where('user_id', $user->id)->count()
        );
    }

    public function test_different_users_can_use_the_same_idempotency_key(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $service = app(GoldOrderService::class);

        $firstOrder = $service->create(
            $firstUser,
            (string) $goldPrice->id,
            '5000.00',
            'shared-order-key'
        );

        $secondOrder = $service->create(
            $secondUser,
            (string) $goldPrice->id,
            '5000.00',
            'shared-order-key'
        );

        $this->assertNotSame($firstOrder->id, $secondOrder->id);

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $firstUser->id)
                ->where('idempotency_key', 'shared-order-key')
                ->count()
        );

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $secondUser->id)
                ->where('idempotency_key', 'shared-order-key')
                ->count()
        );
    }

    public function test_it_rejects_invalid_amounts_without_creating_orders(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $service = app(GoldOrderService::class);

        $invalidAmounts = [
            '0',
            '0.00',
            '-100.00',
            '12.345',
            'abc',
            '10000000000000.00',
        ];

        foreach ($invalidAmounts as $index => $amount) {
            try {
                $service->create(
                    $user,
                    (string) $goldPrice->id,
                    $amount,
                    'invalid-amount-key-'.$index
                );

                $this->fail("Expected validation to reject amount: {$amount}");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(
                    'amount',
                    $exception->errors()
                );
            }
        }

        $this->assertSame(
            0,
            GoldOrder::where('user_id', $user->id)->count()
        );
    }

    public function test_it_rejects_reusing_an_idempotency_key_with_a_different_amount(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $service = app(GoldOrderService::class);

        $service->create(
            $user,
            (string) $goldPrice->id,
            '5000.00',
            'order-key-004'
        );

        try {
            $service->create(
                $user,
                (string) $goldPrice->id,
                '6000.00',
                'order-key-004'
            );

            $this->fail('Expected conflicting idempotency-key reuse to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $user->id)->count()
        );
    }

    public function test_it_rejects_reusing_an_idempotency_key_with_a_different_gold_price(): void
    {
        $user = User::factory()->create();
        $firstGoldPrice = $this->createGoldPrice();
        $secondGoldPrice = $this->createGoldPrice();
        $service = app(GoldOrderService::class);

        $service->create(
            $user,
            (string) $firstGoldPrice->id,
            '5000.00',
            'order-key-005'
        );

        try {
            $service->create(
                $user,
                (string) $secondGoldPrice->id,
                '5000.00',
                'order-key-005'
            );

            $this->fail(
                'Expected conflicting idempotency-key reuse to be rejected.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $user->id)->count()
        );
    }
}
