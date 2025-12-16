<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Import\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Support\Traits\HasAuditColumns;

class Import extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'file',
        'type',
        'description',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'ignored_duplicates',
        'errors',
        'created_by',
        'updated_by',
        'batch_id',
        'period',
        'cutoff_number',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_number' => 'integer',
            'ignored_duplicates' => 'integer',
            'errors' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    /**
     * Obtiene todas las importaciones del mismo batch/tanda
     */
    public function batchImports(): HasMany
    {
        return $this->hasMany(Import::class, 'batch_id', 'batch_id')
            ->where('id', '!=', $this->id);
    }

    /**
     * Scope para obtener importaciones por batch
     */
    public function scopeInBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }
}
