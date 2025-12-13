<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'movement_date',
        'movement_type',
        'operation',
        'source_type',
        'source_id',
        'description',
        'amount',
        'balance_after',
        'status',
        'created_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isCredit(): bool
    {
        if ($this->movement_type) {
            return $this->movement_type === 'credit';
        }

        return (float) $this->amount > 0;
    }

    public function isDebit(): bool
    {
        if ($this->movement_type) {
            return $this->movement_type === 'debit';
        }

        return (float) $this->amount < 0;
    }
}
