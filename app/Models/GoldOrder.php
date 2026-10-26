<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gold_price_id',
        'idempotency_key',
        'amount',
        'price_per_gram',
        'grams',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'price_per_gram' => 'decimal:4',
            'grams' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goldPrice(): BelongsTo
    {
        return $this->belongsTo(GoldPrice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
