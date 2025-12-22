<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Store\Jobs;

use App\Domain\Store\Exports\StoresExport;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class ExportStoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        protected int $userId,
        protected array $filters = [],
        protected ?string $search = null
    ) {
    }

    public function handle(): void
    {
        // 1. Reconstruct Query
        $query = Store::query()
            ->with(['tenderer', 'users']);

        // Apply Search
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('idpos', 'ilike', "%{$search}%")
                    ->orWhere('municipality', 'ilike', "%{$search}%");
            });
        }

        // Apply Filters
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['category'])) {
            $query->where('category', $this->filters['category']);
        }
        if (!empty($this->filters['municipality'])) {
            $query->where('municipality', $this->filters['municipality']);
        }
        if (!empty($this->filters['route_code'])) {
            $query->where('route_code', $this->filters['route_code']);
        }
        if (!empty($this->filters['circuit_code'])) {
            $query->where('circuit_code', $this->filters['circuit_code']);
        }
        if (!empty($this->filters['user_id'])) {
            $userId = $this->filters['user_id'];
            $query->whereHas('users', fn($q) => $q->where('users.id', $userId));
        }

        $query->orderBy('idpos');

        // 2. Generate Excel
        $fileName = 'tiendas-' . now()->format('Y-m-d_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        // Ensure directory exists
        Storage::disk('public')->makeDirectory('exports');

        Excel::store(new StoresExport($query), $filePath, 'public');

        // 3. Notify User
        $user = User::find($this->userId);
        if ($user) {
            $url = Storage::disk('public')->url($filePath);

            Notification::make()
                ->title('Exportación completada')
                ->body('El archivo de tiendas está listo para descargar.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar')
                        ->url($url)
                        ->button()
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user);
        }
    }
}
