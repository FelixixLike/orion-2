<?php

namespace App\Domain\Admin\Filament\Resources\ImportResource\Pages;

use App\Domain\Admin\Filament\Resources\ImportResource;
use App\Domain\Import\Models\Import;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    public function hydrate()
    {
        // Solo hacer polling si hay importaciones pendientes o procesando
        return Import::whereIn('status', ['pending', 'processing'])->exists();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Importacion'),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Import::query()->with('creator');
    }

    /**
     * Get the listeners for this page.
     * Listen for Echo events to refresh table in real-time.
     */
    protected function getListeners(): array
    {
        return [
            'echo:imports,.App.Domain.Import.Events.ImportStatusChanged' => 'refreshTable',
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '2s';
    }

    /**
     * Refresh the table when import status changes.
     */
    public function refreshTable(): void
    {
        // Livewire will automatically refresh the table
    }
}
