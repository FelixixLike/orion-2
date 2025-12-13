<?php

namespace App\Domain\Import\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\User\Models\User;
use App\Domain\Support\Traits\HasAuditColumns;

class SalesCondition extends Model
{
    use HasAuditColumns;

    protected $fillable = [
        'simcard_id',
        'iccid',
        'phone_number',
        'idpos',
        'sale_price',
        'commission_percentage',
        'period_date',
        'period_year',
        'period_month',
        'import_id',
        'population',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'sale_price' => 'decimal:2',
            'period_year' => 'integer',
            'period_month' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $condition): void {
            $condition->syncPeriodFields();
        });
    }

    public function simcard(): BelongsTo
    {
        return $this->belongsTo(Simcard::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function syncPeriodFields(): void
    {
        if ($this->period_year && $this->period_month) {
            return;
        }

        if ($this->period_date) {
            $period = $this->period_date instanceof \DateTimeInterface
                ? $this->period_date
                : new \Illuminate\Support\Carbon($this->period_date);

            $this->period_year = (int) $period->format('Y');
            $this->period_month = (int) $period->format('m');
        }
    }
}
