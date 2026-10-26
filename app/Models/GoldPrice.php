<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_per_gram',
        'currency',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'price_per_gram' => 'decimal:4',
            'fetched_at' => 'datetime',
        ];
    }
}
