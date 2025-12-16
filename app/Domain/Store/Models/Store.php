<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Domain\Support\Traits\HasAuditColumns;

class Store extends Model
{
    use HasFactory;
    use HasAuditColumns;

    /**
     * FIX GLOBAL:
     * Evita que se creen tiendas con idpos = null.
     * Si viene vacío se genera uno automático.
     */
    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->idpos)) {
                // Genera un ID único basado en timestamp
                $store->idpos = 'AUTO-' . now()->format('YmdHisv');
            }
        });
    }

    protected static function newFactory()
    {
        return \Database\Factories\StoreFactory::new();
    }

    protected $fillable = [
        'idpos',
        'id_pdv',
        'name',
        'user_id',
        'category',
        'phone',
        'municipality',
        'route_code',
        'circuit_code',
        'neighborhood',
        'address',
        'email',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // 'category' => StoreCategory::class, // REMOVED: Now dynamic string linked to Model
            // 'municipality' => Municipality::class, // REMOVED: Now dynamic string linked to Model
            'status' => StoreStatus::class,
        ];
    }

    /**
     * @deprecated Relación 1:1 histórica (stores.user_id).
     * Usar la relación muchos-a-muchos users() vía store_user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tendero principal (usa stores.user_id como FK legacy)
     */
    public function tenderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación muchos-a-muchos con usuarios (tenderos) mediante la tabla store_user.
     * user_id está deprecado pero se mantiene por compatibilidad.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')->withTimestamps();
    }

    public function liquidations(): HasMany
    {
        return $this->hasMany(\App\Domain\Store\Models\Liquidation::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(\App\Domain\Store\Models\Redemption::class);
    }

    public function balanceMovements(): HasMany
    {
        return $this->hasMany(\App\Domain\Store\Models\BalanceMovement::class);
    }

    /**
     * Obtiene las Sales Conditions vinculadas mediante idpos.
     */
    public function salesConditions(): HasMany
    {
        return $this->hasMany(
            \App\Domain\Import\Models\SalesCondition::class,
            'idpos',
            'idpos'
        );
    }
}
