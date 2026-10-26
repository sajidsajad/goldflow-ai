<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gold_orders', function (Blueprint $table) {
            $table->dropUnique('gold_orders_idempotency_key_unique');

            $table->unique(
                ['user_id', 'idempotency_key'],
                'gold_orders_user_id_idempotency_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('gold_orders', function (Blueprint $table) {
            $table->dropUnique(
                'gold_orders_user_id_idempotency_key_unique'
            );

            $table->unique('idempotency_key');
        });
    }
};
