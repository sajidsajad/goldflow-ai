<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'gold_order_id',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'attempt_number',
        'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'attempt_number' => 'integer',
            'provider_response' => 'array',
        ];
    }

    public function goldOrder(): BelongsTo
    {
        return $this->belongsTo(GoldOrder::class);
    }
}
