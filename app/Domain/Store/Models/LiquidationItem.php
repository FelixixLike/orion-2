<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Store\Models;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Models\Simcard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Support\Traits\HasAuditColumns;

class LiquidationItem extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $fillable = [
        'liquidation_id',
        'simcard_id',
        'phone_number',
        'iccid',
        'commission_status',
        'activation_date',
        'cutoff_date',
        'custcode',
        'operator_report_id',
        'sales_condition_id',
        'total_commission',
        'operator_total_recharge',
        'movilco_recharge_amount',
        'discount_total_period',
        'discount_residual',
        'base_liquidation_final',
        'recharge_discount',
        'commission_after_discount',
        'liquidation_multiplier',
        'final_amount',
        'period_date',
        'period',
        'liquidation_month',
        'sim_value',
        'residual_percentage',
        'transfer_percentage',
        'residual_payment',
        'idpos',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_commission' => 'decimal:2',
            'operator_total_recharge' => 'decimal:2',
            'movilco_recharge_amount' => 'decimal:2',
            'discount_total_period' => 'decimal:2',
            'discount_residual' => 'decimal:2',
            'base_liquidation_final' => 'decimal:2',
            'recharge_discount' => 'decimal:2',
            'commission_after_discount' => 'decimal:2',
            'liquidation_multiplier' => 'decimal:6',
            'final_amount' => 'decimal:2',
            'period_date' => 'date',
            'activation_date' => 'date',
            'cutoff_date' => 'date',
            'sim_value' => 'decimal:2',
            'residual_percentage' => 'decimal:3',
            'transfer_percentage' => 'decimal:3',
            'residual_payment' => 'decimal:2',
        ];
    }

    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(Liquidation::class);
    }

    public function simcard(): BelongsTo
    {
        return $this->belongsTo(Simcard::class);
    }

    public function operatorReport(): BelongsTo
    {
        return $this->belongsTo(OperatorReport::class);
    }

    public function salesCondition(): BelongsTo
    {
        return $this->belongsTo(SalesCondition::class);
    }
}
