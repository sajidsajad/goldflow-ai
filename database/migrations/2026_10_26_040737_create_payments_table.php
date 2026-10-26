
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gold_order_id')
                ->constrained('gold_orders')
                ->restrictOnDelete();

            $table->string('provider', 50)->default('simulator');
            $table->string('provider_reference', 100)->nullable()->unique();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('INR');

            $table->string('status', 30)->default('pending')->index();
            $table->unsignedSmallInteger('attempt_number')->default(1);

            $table->json('provider_response')->nullable();

            $table->timestamps();

            $table->index(['gold_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
