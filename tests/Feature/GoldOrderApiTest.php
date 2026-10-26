<?php

namespace Tests\Feature;

use App\Models\GoldOrder;
use App\Models\GoldPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoldOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function createGoldPrice(): GoldPrice
    {
        return GoldPrice::create([
            'price_per_gram' => '10250.0000',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);
    }

    public function test_authenticated_user_can_create_a_gold_order(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', '5000.00')
            ->assertJsonPath('data.price_per_gram', '10250.0000')
            ->assertJsonPath('data.grams', '0.48780487')
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('gold_orders', [
            'user_id' => $user->id,
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'status' => 'pending',
        ]);
    }

    public function test_guest_cannot_create_a_gold_order(): void
    {
        $goldPrice = $this->createGoldPrice();

        $response = $this->postJson('/api/orders', [
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('gold_orders', 0);
    }

    public function test_invalid_amount_is_rejected_by_the_api(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'gold_price_id' => $goldPrice->id,
            'amount' => '-100.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('gold_orders', 0);
    }

    public function test_repeating_an_idempotency_key_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $key = (string) Str::uuid();

        $payload = [
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => $key,
        ];

        $firstResponse = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        $firstResponse->assertCreated();

        $secondResponse = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        $secondResponse
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $firstResponse->json('data.id')
            );

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $user->id)
                ->where('idempotency_key', $key)
                ->count()
        );
    }

    public function test_client_cannot_choose_another_users_id(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $goldPrice = $this->createGoldPrice();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'user_id' => $anotherUser->id,
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingPath('data.user_id');

        $order = GoldOrder::firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertNotSame($anotherUser->id, $order->user_id);
    }

    public function test_reusing_an_idempotency_key_with_a_different_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();
        $key = (string) Str::uuid();

        $payload = [
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => $key,
        ];

        $this->actingAs($user)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $payload['amount'] = '6000.00';

        $this->actingAs($user)
            ->postJson('/api/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->assertSame(
            1,
            GoldOrder::where('user_id', $user->id)
                ->where('idempotency_key', $key)
                ->count()
        );
    }

    public function test_nonexistent_gold_price_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/orders', [
                'gold_price_id' => 999999,
                'amount' => '5000.00',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gold_price_id']);

        $this->assertDatabaseCount('gold_orders', 0);
    }

    public function test_web_session_authenticated_user_can_create_an_order_through_the_api(): void
    {
        $user = User::factory()->create();
        $goldPrice = $this->createGoldPrice();

        $this->actingAs($user, 'web'); // explicitly authenticates through Laravel's web guard

        $this->postJson('/api/orders', [
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
            'idempotency_key' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.amount', '5000.00');

        $this->assertDatabaseHas('gold_orders', [
            'user_id' => $user->id,
            'gold_price_id' => $goldPrice->id,
            'amount' => '5000.00',
        ]);
    }
}
