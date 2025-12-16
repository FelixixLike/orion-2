<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\ImportResource\Widgets;

use App\Domain\Import\Models\Import;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ImportStatsOverview extends BaseWidget
{
    public ?Import $record = null;

    protected ?string $pollingInterval = '2s';

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $failed = $this->record->failed_rows ?? 0;
        $processed = $this->record->processed_rows ?? 0;
        $success = max(0, $processed - $failed);

        return [
            Stat::make('Estado', match ($this->record->status) {
                'processing' => 'Procesando...',
                'completed' => 'Completado',
                'failed' => 'Fallido',
                default => ucfirst($this->record->status),
            })
                ->color(match ($this->record->status) {
                    'processing' => 'warning',
                    'completed' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),

            Stat::make('Progreso', number_format($processed) . ' / ' . number_format($this->record->total_rows))
                ->description('Filas procesadas')
                ->chart([$processed, $this->record->total_rows])
                ->color('info'),

            Stat::make('Exitosos', number_format($success))
                ->color('success'),

            Stat::make('Duplicados/Saltados', number_format($failed))
                ->color('danger'),
        ];
    }
}
