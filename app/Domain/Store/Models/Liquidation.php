<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Store\Models\LiquidationItem;
use App\Domain\Support\Traits\HasAuditColumns;

class Liquidation extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $fillable = [
        'store_id',
        'period_year',
        'period_month',
        'version',
        'gross_amount',
        'net_amount',
        'status',
        'clarifications',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LiquidationItem::class);
    }
}
