<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Exports\SalesConditionsExport;
use App\Domain\Import\Models\SalesCondition;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExportSalesConditionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes

    public function __construct(
        protected int $userId,
        protected array $filters = [],
        protected ?string $search = null
    ) {
    }

    public function handle(): void
    {
        // 1. Reconstruct Query
        $query = SalesCondition::query();

        // Apply Search
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('iccid', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'ilike', "%{$search}%")
                    ->orWhere('idpos', 'ilike', "%{$search}%")
                    ->orWhere('population', 'ilike', "%{$search}%")
                    ->orWhereHas('creator', function ($sq) use ($search) {
                        $sq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Apply Filters
        if (!empty($this->filters['idpos'])) {
            $query->where('idpos', $this->filters['idpos']);
        }

        if (!empty($this->filters['residual_range'])) {
            $range = $this->filters['residual_range'];
            if ($range === 'lt3')
                $query->where('commission_percentage', '<', 3);
            if ($range === '3to7')
                $query->whereBetween('commission_percentage', [3, 7]);
            if ($range === 'gt7')
                $query->where('commission_percentage', '>', 7);
        }

        if (!empty($this->filters['period_range'])) {
            $dates = $this->filters['period_range'];
            if (!empty($dates['from']))
                $query->whereDate('period_date', '>=', $dates['from']);
            if (!empty($dates['to']))
                $query->whereDate('period_date', '<=', $dates['to']);
        }

        if (!empty($this->filters['store_status'])) {
            $status = $this->filters['store_status'];
            if ($status === 'with_store') {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('stores')
                        ->whereColumn('stores.idpos', 'sales_conditions.idpos');
                });
            } elseif ($status === 'no_store') {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('stores')
                        ->whereColumn('stores.idpos', 'sales_conditions.idpos');
                });
            }
        }

        $query->orderByDesc('period_year');

        // 2. Generate Excel
        $fileName = 'condiciones_sim_' . now()->format('Y-m-d_His') . '.csv';
        $filePath = 'exports/' . $fileName;

        // Ensure directory exists
        Storage::disk('public')->makeDirectory('exports');

        // Export as CSV for performance
        Excel::store(new SalesConditionsExport($query), $filePath, 'public', \Maatwebsite\Excel\Excel::CSV);

        // 3. Notify User
        $user = User::find($this->userId);
        if ($user) {
            $url = Storage::disk('public')->url($filePath);

            Notification::make()
                ->title('Exportación completada')
                ->body('El archivo de condiciones SIM está listo para descargar.')
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
