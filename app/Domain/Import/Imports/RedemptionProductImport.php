<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Imports;

use App\Domain\Import\Services\DataNormalizerService;
use App\Domain\Store\Models\RedemptionProduct;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class RedemptionProductImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets
{
    use Importable;

    private int $processed = 0;
    private int $inserted = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private array $skippedRows = [];

    private const ALLOWED_TYPES = ['sim', 'recharge', 'device', 'accessory'];

    public function __construct(
        private readonly int $importId
    ) {}

    public function sheets(): array
    {
        return [
            'Tabla' => $this,
        ];
    }

    public function onRow(Row $row): void
    {
        $this->processed++;

        $normalized = DataNormalizerService::normalizeKeys($row->toArray());
        $name = DataNormalizerService::cleanString($normalized['name'] ?? null);
        $type = strtolower((string) DataNormalizerService::cleanString($normalized['type'] ?? '') ?? '');

        if (! $name || ! in_array($type, self::ALLOWED_TYPES, true)) {
            $this->markSkipped($row->getIndex(), 'Nombre o tipo inválido');
            return;
        }

        $description = DataNormalizerService::cleanString($normalized['description'] ?? null);
        $unitValue = DataNormalizerService::parseDecimal($normalized['unit_value'] ?? null) ?? 0.0;
        $stock = (int) ($normalized['stock'] ?? 0);
        $monthlyLimit = (int) ($normalized['monthly_store_limit'] ?? 0);
        $maxValue = DataNormalizerService::parseDecimal($normalized['max_value'] ?? null);
        $isActive = $this->toBoolean($normalized['is_active'] ?? true);

        $payload = [
            'type' => $type,
            'description' => $description,
            'unit_value' => $unitValue,
            'stock' => in_array($type, ['recharge', 'sim']) ? null : $stock,
            'monthly_store_limit' => $type === 'sim' ? $monthlyLimit : null,
            'max_value' => $type === 'recharge' ? $maxValue : null,
            'is_active' => $isActive,
            'image_url' => 'images/store/item.png',
        ];

        try {
            $existing = RedemptionProduct::where('name', $name)->first();

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                RedemptionProduct::create(array_merge(['name' => $name], $payload));
                $this->inserted++;
            }
        } catch (\Throwable $e) {
            Log::error('RedemptionProductImport: error procesando fila', [
                'import_id' => $this->importId,
                'row' => $row->getIndex(),
                'error' => $e->getMessage(),
            ]);
            $this->markSkipped($row->getIndex(), 'Error guardando el producto: ' . $e->getMessage());
        }
    }

    public function getStats(): array
    {
        return [
            'total_processed' => $this->processed,
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }

    public function getErrors(): array
    {
        if (empty($this->skippedRows)) {
            return [];
        }

        return [
            'skipped_rows' => $this->skippedRows,
            'message' => 'Algunas filas no pudieron importarse. Revisa el detalle y corrige los datos.',
        ];
    }

    private function markSkipped(int $rowNumber, string $message): void
    {
        $this->skipped++;
        if (count($this->skippedRows) >= 50) {
            return;
        }

        $this->skippedRows[] = [
            'row' => $rowNumber,
            'reason' => $message,
        ];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $stringValue = strtolower((string) $value);

        return in_array($stringValue, ['1', 'true', 'si', 'sí', 'activo', 'yes'], true);
    }
}
