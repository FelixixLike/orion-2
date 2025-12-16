<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Import\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Support\Traits\HasAuditColumns;

/**
 * @property int $id
 * @property string|null $iccid
 * @property string|null $phone_number
 */
class Simcard extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'iccid',
        'phone_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'iccid' => 'string',
        'phone_number' => 'string',
    ];

    public function operatorReports(): HasMany
    {
        return $this->hasMany(OperatorReport::class);
    }

    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }

    public function salesConditions(): HasMany
    {
        return $this->hasMany(SalesCondition::class);
    }
}
