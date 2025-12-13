<?php

namespace App\Domain\Import\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Support\Traits\HasAuditColumns;

class OperatorReport extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'simcard_id',
        'phone_number',
        'city_code',
        'coid',
        'commission_status',
        'activation_date',
        'cutoff_date',
        'commission_paid_80',
        'commission_paid_20',
        'recharge_amount',
        'recharge_period',
        'payment_percentage',
        'custcode',
        'total_recharge_per_period',
        'total_commission',
        'import_id',
        'liquidation_item_id',
        'period_year',
        'period_month',
        'period_label',
        'is_consolidated',
        'cutoff_numbers',
        'total_paid',
        'calculated_amount',
        'amount_difference',
        'raw_payload',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activation_date' => 'date',
            'cutoff_date' => 'date',
            'commission_paid_80' => 'decimal:2',
            'commission_paid_20' => 'decimal:2',
            'recharge_amount' => 'decimal:2',
            'total_recharge_per_period' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'calculated_amount' => 'decimal:2',
            'amount_difference' => 'decimal:2',
            'payment_percentage' => 'float',
            'period_year' => 'integer',
            'period_month' => 'integer',
            'is_consolidated' => 'boolean',
            'cutoff_numbers' => 'array',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Total de comisión pagada por Claro (80 + 20). Campo virtual para no tocar el esquema.
     */
    public function getTotalCommissionAttribute(): float
    {
        $stored = $this->getAttributeFromArray('total_commission');
        if ($stored !== null) {
            return (float) $stored;
        }

        return (float) ($this->commission_paid_80 ?? 0) + (float) ($this->commission_paid_20 ?? 0);
    }

    /**
     * Importe pagado por el operador en el periodo (mapea al total_recharge_per_period).
     */
    public function getOperatorAmountAttribute(): ?float
    {
        return $this->total_recharge_per_period;
    }

    public function getPaymentPercentageAttribute(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return $value > 1 ? $value / 100 : $value;
    }

    public function simcard(): BelongsTo
    {
        return $this->belongsTo(Simcard::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function liquidationItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Store\Models\LiquidationItem::class);
    }
}
