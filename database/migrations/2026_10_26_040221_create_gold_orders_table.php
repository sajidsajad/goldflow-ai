
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('gold_price_id')
                ->constrained('gold_prices')
                ->restrictOnDelete();

            $table->uuid('idempotency_key')->unique();

            $table->decimal('amount', 15, 2);
            $table->decimal('price_per_gram', 15, 4);
            $table->decimal('grams', 20, 8);

            $table->string('currency', 3)->default('INR');

            $table->string('status', 30)->default('pending')->index();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_orders');
    }
};
