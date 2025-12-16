<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Import\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Support\Traits\HasAuditColumns;

class Recharge extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'simcard_id',
        'iccid',
        'phone_number',
        'recharge_amount',
        'period_date',
        'period_year',
        'period_month',
        'period_label',
        'import_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'recharge_amount' => 'decimal:2',
            'period_year' => 'integer',
            'period_month' => 'integer',
        ];
    }

    public function simcard(): BelongsTo
    {
        return $this->belongsTo(Simcard::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
