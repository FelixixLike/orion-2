<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('balance_movements', 'operation')) {
                $table->string('operation', 32)->nullable()->after('movement_type');
            }
        });

        DB::table('balance_movements')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $currentType = strtolower((string) ($row->movement_type ?? ''));
                    $operation = $row->operation ?? $currentType;
                    [$movementType, $finalOperation] = $this->normalizeMovementType($currentType, (float) $row->amount, $operation);

                    DB::table('balance_movements')
                        ->where('id', $row->id)
                        ->update([
                            'operation' => $finalOperation,
                            'movement_type' => $movementType,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('balance_movements', 'operation')) {
            DB::table('balance_movements')
                ->whereNotNull('operation')
                ->update(['movement_type' => DB::raw('operation')]);
        }

        Schema::table('balance_movements', function (Blueprint $table) {
            if (Schema::hasColumn('balance_movements', 'operation')) {
                $table->dropColumn('operation');
            }
        });
    }

    private function normalizeMovementType(?string $currentType, float $amount, ?string $operation): array
    {
        $type = strtolower((string) $currentType);
        $op = $operation ?: $type;

        // Solo normalizamos cuando el origen es conocido; evitamos inferir ciegamente por monto.
        return match ($type) {
            'credit', 'debit' => [$type, $op ?: $type],
            'liquidation' => ['credit', 'liquidation'],
            'redemption' => ['debit', 'redemption'],
            'refund' => ['debit', 'refund'],
            'adjustment' => [$amount < 0 ? 'debit' : 'credit', 'adjustment'],
            default => [$type ?: 'legacy', $op ?: 'legacy'], // Se marca como legacy para revision manual.
        };
    }
};
