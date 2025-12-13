<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use App\Domain\Retailer\Support\BalanceService;
use App\Domain\User\Models\User;
use App\Domain\Store\Support\BalanceMovementRecorder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;
use App\Domain\Support\Traits\HasAuditColumns;

class Redemption extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $fillable = [
        'store_id',
        'liquidation_id',
        'redemption_product_id',
        'quantity',
        'total_value',
        'requested_at',
        'status',
        'handled_by_user_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'requested_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(Liquidation::class);
    }

    public function redemptionProduct(): BelongsTo
    {
        return $this->belongsTo(RedemptionProduct::class);
    }

    public function handledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Redemption $redemption) {
            if (!$redemption->requested_at) {
                $redemption->requested_at = Carbon::now();
            }

            // Calcular total si viene vacÍo; asume unit_value en el producto.
            if (!$redemption->total_value && $redemption->redemption_product_id) {
                $product = $redemption->redemptionProduct()->first();
                if ($product) {
                    $qty = $redemption->quantity ?: 1;
                    $redemption->total_value = (float) $product->unit_value * (int) $qty;
                }
            }
        });

        static::created(function (Redemption $redemption) {
            $redemption->recordMovementIfNeeded(null);
        });

        static::updated(function (Redemption $redemption) {
            if ($redemption->wasChanged('status')) {
                $redemption->recordMovementIfNeeded($redemption->getOriginal('status'));
            }
        });
    }

    /**
     * Registra movimiento de saldo al aprobar/entregar/confirmar.
     */
    protected function recordMovementIfNeeded(?string $originalStatus): void
    {
        $status = $this->status;
        $debitStatuses = ['approved', 'delivered', 'confirmed'];
        $wasDebit = in_array($originalStatus, $debitStatuses, true);
        $isDebit = in_array($status, $debitStatuses, true);

        // CASO 1: Entrando a estado debito (Cobrar y descontar stock)
        // Solo registrar cuando se ingresa por primera vez a un estado debito.
        if ($isDebit && !$wasDebit) {
            $this->loadMissing('store', 'redemptionProduct');

            // Validar saldo disponible al aprobar/entregar.
            $balanceService = app(BalanceService::class);
            $available = $balanceService->getStoreBalance($this->store_id);
            if ($this->total_value > $available) {
                throw new RuntimeException('Saldo insuficiente para completar la redención.');
            }

            // Validar stock antes de descontar y registrar movimiento.
            if ($this->redemptionProduct && $this->redemptionProduct->stock !== null) {
                if ($this->redemptionProduct->stock < $this->quantity) {
                    throw new RuntimeException('No hay stock suficiente para esta redención.');
                }

                $this->redemptionProduct->decrement('stock', (int) $this->quantity);
            }

            app(BalanceMovementRecorder::class)->recordRedemption($this);
            return;
        }

        // CASO 2: Saliendo de estado debito (Reembolsar y devolver stock)
        // Ej: Aprobada -> Rechazada/Cancelada
        if ($wasDebit && !$isDebit) {
            $this->loadMissing('redemptionProduct');

            // Devolver Stock
            if ($this->redemptionProduct && $this->redemptionProduct->stock !== null) {
                $this->redemptionProduct->increment('stock', (int) $this->quantity);
            }

            // Reembolsar Saldo
            app(BalanceMovementRecorder::class)->recordAdjustment(
                $this->store_id,
                "Reembolso por redención {$status}: " . ($this->redemptionProduct?->name ?? 'Producto'),
                (float) $this->total_value,
                Carbon::now(),
                'refund',
                $this->id
            );
        }
    }
}
