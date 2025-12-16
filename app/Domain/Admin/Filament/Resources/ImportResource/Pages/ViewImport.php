<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\ImportResource\Pages;

use App\Domain\Admin\Filament\Resources\ImportResource;
use App\Domain\Import\Models\Import;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Models\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use App\Domain\Import\Models\SalesCondition;
use Illuminate\Support\Carbon;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Notifications\Notification;
use App\Domain\Admin\Filament\Resources\ImportResource\Widgets\ImportStatsOverview;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ImportStatsOverview::class,
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Text::make('id_label')
                                    ->content(fn($record) => 'ID: ' . $record->id),

                                Text::make('status_label')
                                    ->content(fn(Import $record) => 'Estado: ' . match ($record->status) {
                                        'pending' => 'Pendiente',
                                        'processing' => 'Procesando',
                                        'completed' => 'Completado',
                                        'failed' => 'Fallido',
                                        default => $record->status,
                                    }),

                                Text::make('type_label')
                                    ->content(fn(Import $record) => 'Tipo: ' . match ($record->type) {
                                        'operator_report' => 'Reporte del Operador',
                                        'recharge' => 'Recargas Variables',
                                        'sales_condition' => 'Condiciones SIM',
                                        'store' => 'Tiendas',
                                        default => $record->type,
                                    }),
                            ]),

                        Grid::make(5)
                            ->schema([
                                Text::make('total_rows_label')
                                    ->content(fn(Import $record) => 'Total: ' . number_format($record->total_rows ?? 0)),

                                Text::make('processed_rows_label')
                                    ->content(fn(Import $record) => 'Procesados: ' . number_format($record->processed_rows ?? 0)),

                                Text::make('successful_rows_label')
                                    ->content(fn(Import $record) => 'Exitosos: ' . number_format(max(0, ($record->processed_rows ?? 0) - ($record->failed_rows ?? 0))))
                                    ->color('success'),

                                Text::make('duplicates_label')
                                    ->content(fn(Import $record) => 'Duplicados: ' . number_format($record->errors['summary']['duplicates'] ?? 0))
                                    ->color('warning'),

                                Text::make('error_rows_label')
                                    ->content(fn(Import $record) => 'Fallidos Total: ' . number_format($record->failed_rows ?? 0))
                                    ->color('danger'),
                            ]),

                        Text::make('date_label')
                            ->content(fn(Import $record) => 'Fecha: ' . $record->created_at->format('d/m/Y H:i A')),
                    ]),

                Section::make('Conflictos Pendientes')
                    ->schema([
                        View::make('errors.conflicts')
                            ->view('filament.admin.imports.store-conflicts'),
                    ])
                    ->visible(fn(Import $record) => !empty($record->errors['conflicts'] ?? [])),

                Section::make('Detalle de Reporte')
                    ->schema([
                        View::make('filament.admin.imports.error-details'),
                    ])
                    ->visible(fn(Import $record) => !empty($record->errors) && count($record->errors) > 0),
            ]);
    }

    protected function formatErrors($errors): string
    {
        return ''; // Deprecated, using View component
    }

    // --- Lógica de Resolución de Conflictos ---

    public function resolveStoreConflictFromImport(int $index, string $action): void
    {
        $errors = $this->record->errors ?? [];
        $conflicts = $errors['conflicts'] ?? [];

        if (!isset($conflicts[$index])) {
            return;
        }

        $conflict = $conflicts[$index];

        if ($action === 'update') {
            $this->applyConflict($conflict);
        }

        $user = auth('admin')->user();
        $userName = $user ? $user->name : 'Sistema';
        $userDoc = $user ? $user->id_number : 'N/A';

        $conflicts[$index]['status'] = 'resolved';
        $conflicts[$index]['resolution'] = $action;
        $conflicts[$index]['resolved_by'] = $userName;
        $conflicts[$index]['resolved_by_doc'] = $userDoc;
        $conflicts[$index]['resolved_at'] = now()->toDateTimeString();

        $errors['conflicts'] = $conflicts;

        $this->record->errors = $errors;
        $this->record->save();
        $this->record->refresh();

        Notification::make()
            ->title($action === 'update' ? 'Aplicado' : 'Omitido')
            ->body($action === 'update' ? 'Se aplicaron los cambios del conflicto.' : 'Se omitió el conflicto.')
            ->success()
            ->send();
    }

    public function resolveAllStoreConflicts(string $action): void
    {
        $errors = $this->record->errors ?? [];
        $conflicts = $errors['conflicts'] ?? [];
        $count = 0;

        $user = auth('admin')->user();
        $userName = $user ? $user->name : 'Sistema';
        $userDoc = $user ? $user->id_number : 'N/A';
        $now = now()->toDateTimeString();

        foreach ($conflicts as $index => $conflict) {
            if (($conflict['status'] ?? 'pending') !== 'resolved') {
                if ($action === 'update') {
                    $this->applyConflict($conflict);
                }

                $conflicts[$index]['status'] = 'resolved';
                $conflicts[$index]['resolution'] = $action;
                $conflicts[$index]['resolved_by'] = $userName;
                $conflicts[$index]['resolved_by_doc'] = $userDoc;
                $conflicts[$index]['resolved_at'] = $now;
                $count++;
            }
        }

        if ($count > 0) {
            $errors['conflicts'] = $conflicts;
            $this->record->errors = $errors;
            $this->record->save();
            $this->record->refresh();

            Notification::make()
                ->title($action === 'update' ? 'Aplicado Todo' : 'Omitido Todo')
                ->success()
                ->body("Se procesaron {$count} conflictos masivamente.")
                ->send();
        } else {
            Notification::make()
                ->title('Sin cambios')
                ->info()
                ->body("No habían conflictos pendientes.")
                ->send();
        }
    }

    protected function applyConflict(array $conflict): void
    {
        switch ($conflict['type'] ?? null) {
            case 'user_conflict':
                $this->applyUserConflict($conflict);
                break;
            case 'store_conflict':
                $this->applyStoreConflict($conflict);
                break;
            case 'sales_condition_conflict':
                $this->applySalesConflict($conflict);
                break;
            default:
                break;
        }
    }

    protected function applyUserConflict(array $conflict): void
    {
        $doc = $conflict['id_number'] ?? null;
        if (!$doc)
            return;

        $user = User::where('id_number', $doc)->first();
        if (!$user)
            return;

        $incoming = $conflict['incoming'] ?? [];

        $firstName = $incoming['first_name'] ?? null;
        $lastName = $incoming['last_name'] ?? null;

        if (!$firstName && !empty($incoming['name'])) {
            // Fallback: Parse from full name
            $parts = explode(' ', trim($incoming['name']));
            if (count($parts) > 1) {
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
            } else {
                $firstName = $incoming['name'];
                $lastName = '';
            }
        }

        $user->update([
            'first_name' => $firstName ?? $user->first_name,
            'last_name' => $lastName ?? $user->last_name,
            'email' => $incoming['email'] ?? $user->email,
            'phone' => $incoming['phone'] ?? $user->phone,
        ]);

        if (!empty($conflict['idpos'])) {
            $store = Store::where('idpos', $conflict['idpos'])->first();
            if ($store) {
                $store->update(['user_id' => $user->id, 'status' => StoreStatus::ACTIVE]);
                $store->users()->sync([$user->id]);
            }
        }
    }

    protected function applyStoreConflict(array $conflict): void
    {
        $store = null;
        if (!empty($conflict['store_id'])) {
            $store = Store::find($conflict['store_id']);
        }
        if (!$store && !empty($conflict['idpos'])) {
            $store = Store::where('idpos', $conflict['idpos'])->first();
        }
        if (!$store)
            return;

        $incoming = $conflict['incoming'] ?? [];
        $updates = [];

        if (!empty($incoming['name']))
            $updates['name'] = $incoming['name'];
        if (!empty($incoming['category']))
            $updates['category'] = $incoming['category']; // Ya viene el nombre directo
        if (!empty($incoming['municipality']))
            $updates['municipality'] = $incoming['municipality']; // Ya viene el nombre directo
        if (!empty($incoming['circuit']))
            $updates['circuit_code'] = $incoming['circuit'];
        if (!empty($incoming['route']))
            $updates['route_code'] = $incoming['route'];

        if (!empty($updates)) {
            $store->update($updates);
        }
    }

    protected function applySalesConflict(array $conflict): void
    {
        // Lógica futura
    }

    // parseCategory y parseMunicipality ya no son necesarios dado que usamos strings y modelos dinámicos en el StoreImport
    // Se eliminan para evitar confusión y errores con Enums que ya no existen o no se usan.
}
