<?php

namespace Tests\Feature;

use App\Models\GoldPrice;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoldPriceTest extends TestCase
{
    public function test_it_stores_a_gold_price_with_its_currency_and_source(): void
    {
        $price = GoldPrice::create([
            'price_per_gram' => '10250.1250',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);

        $this->assertDatabaseHas('gold_prices', [
            'id' => $price->id,
            'price_per_gram' => '10250.1250',
            'currency' => 'INR',
            'source' => 'test-simulator',
        ]);
    }

    public function test_it_preserves_price_decimal_precision_and_casts_fetched_at(): void
    {
        $price = GoldPrice::create([
            'price_per_gram' => '10250.1250',
            'currency' => 'INR',
            'source' => 'test-simulator',
            'fetched_at' => now(),
        ]);

        $price->refresh();

        $this->assertSame('10250.1250', $price->price_per_gram);
        $this->assertInstanceOf(
            Carbon::class,
            $price->fetched_at
        );
    }
}
