<?php

namespace Tests\Feature;

use App\Models\GoldOrder;
use App\Models\GoldPrice;
use App\Models\Payment;
use App\Models\User;
use Tests\TestCase;

class GoldOrderRelationshipTest extends TestCase
{
    public function test_order_belongs_to_user_and_gold_price(): void
    {
        $user = User::factory()->create();

        $goldPrice = GoldPrice::create([
            'price_per_gram' => '10250.1250',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);

        $order = GoldOrder::create([
            'user_id' => $user->id,
            'gold_price_id' => $goldPrice->id,
            'idempotency_key' => 'test-order-001',
            'amount' => '5000.00',
            'price_per_gram' => '10250.1250',
            'grams' => '0.48780488',
            'currency' => 'INR',
            'status' => 'pending',
        ]);

        $this->assertTrue($order->user->is($user));
        $this->assertTrue($order->goldPrice->is($goldPrice));
    }

    public function test_order_has_many_payments_and_payment_belongs_to_order(): void
    {
        $user = User::factory()->create();

        $goldPrice = GoldPrice::create([
            'price_per_gram' => '10250.1250',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);

        $order = GoldOrder::create([
            'user_id' => $user->id,
            'gold_price_id' => $goldPrice->id,
            'idempotency_key' => 'test-order-002',
            'amount' => '5000.00',
            'price_per_gram' => '10250.1250',
            'grams' => '0.48780488',
            'currency' => 'INR',
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'gold_order_id' => $order->id,
            'provider' => 'simulator',
            'provider_reference' => 'payment-test-001',
            'amount' => '5000.00',
            'currency' => 'INR',
            'status' => 'pending',
            'attempt_number' => 1,
            'provider_response' => ['test' => true],
        ]);

        $this->assertTrue($order->payments->contains($payment));
        $this->assertTrue($payment->goldOrder->is($order));
    }
}
