<?php

namespace App\Domain\Admin\Filament\Resources\ImportResource\Pages;

use App\Domain\Admin\Filament\Resources\ImportResource;
use App\Domain\Import\Models\Import;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use App\Domain\Import\Models\SalesCondition;
use Illuminate\Support\Carbon;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    // Actualizar automáticamente cada 3 segundos si está procesando
    protected ?string $pollingInterval = '3s';

    public function shouldPoll(): bool
    {
        /** @var Import $record */
        $record = $this->record;

        // Solo hacer polling si está pendiente o procesando
        return in_array($record->status, ['pending', 'processing']);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending' => 'warning',
                                'processing' => 'info',
                                'completed' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'pending' => 'Pendiente',
                                'processing' => 'Procesando',
                                'completed' => 'Completado',
                                'failed' => 'Fallido',
                                default => $state,
                            }),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'operator_report' => 'info',
                                'recharge' => 'success',
                                'sales_condition' => 'warning',
                                'store' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'operator_report' => 'Reporte del Operador',
                                'recharge' => 'Recargas Variables',
                                'sales_condition' => 'Condiciones SIM',
                                'store' => 'Tiendas',
                                default => $state,
                            }),

                        TextEntry::make('created_at')
                            ->label('Fecha de Creación')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('creator.name')
                            ->label('Creado por')
                            ->default('N/A')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpanFull()
                            ->visible(fn(?string $state): bool => !empty($state)),

                        TextEntry::make('batch_id')
                            ->label('Tanda')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn(?string $state): string => $state ? $state : 'Individual')
                            ->copyable()
                            ->columnSpanFull()
                            ->visible(fn(?string $state): bool => !empty($state)),
                    ])
                    ->columns(3),

                Section::make('Progreso')
                    ->schema([
                        TextEntry::make('total_rows')
                            ->label('Total de Filas')
                            ->numeric()
                            ->placeholder('0'),

                        TextEntry::make('processed_rows')
                            ->label('Filas Procesadas')
                            ->numeric()
                            ->placeholder('0'),

                        TextEntry::make('failed_rows')
                            ->label('Filas Fallidas')
                            ->numeric()
                            ->badge()
                            ->color(fn(?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'success')
                            ->placeholder('0'),
                    ])
                    ->columns(3)
                    ->visible(fn(Import $record): bool => $record->status !== 'pending'),

                Section::make('Resumen de Importación')
                    ->description('Estadísticas del proceso de importación')
                    ->schema([
                        TextEntry::make('errors.summary.inserted')
                            ->label('Registros Insertados')
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->default(0),

                        TextEntry::make('errors.summary.skipped')
                            ->label('Filas Saltadas')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-exclamation-circle')
                            ->default(0),

                        TextEntry::make('errors.summary.duplicates')
                            ->label('Duplicados')
                            ->badge()
                            ->color('danger')
                            ->icon('heroicon-o-x-circle')
                            ->default(0),

                        TextEntry::make('errors.summary.total_processed')
                            ->label('Total Procesado')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-document-text')
                            ->default(0),
                    ])
                    ->columns(4)
                    ->visible(fn(Import $record): bool => !empty($record->errors['summary'] ?? null)),

                Section::make('Filas Saltadas')
                    ->description(fn(Import $record) => "Se saltaron {$record->errors['skipped']['count']} fila(s) por datos inválidos")
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('errors.skipped.rows')
                            ->label('Detalle')
                            ->schema([
                                TextEntry::make('row')
                                    ->label('Fila')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('reason_label')
                                    ->label('Razón')
                                    ->badge()
                                    ->color('warning'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('warning')
                    ->visible(fn(Import $record): bool => !empty($record->errors['skipped'] ?? null)),

                Section::make('Registros Duplicados')
                    ->description(fn(Import $record) => "Se encontraron " . ($record->errors['duplicates']['count'] ?? 0) . " registro(s) duplicado(s)")
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('errors.duplicates.rows')
                            ->label('Detalle')
                            ->schema([
                                TextEntry::make('row')
                                    ->label('Fila')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->copyable(),
                                TextEntry::make('coid')
                                    ->label('COID')
                                    ->copyable(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->visible(fn(Import $record): bool => !empty($record->errors['duplicates'] ?? null)),

                Section::make('Conflictos de tiendas')
                    ->description('Aplica u omite los cambios detectados en la importación de tiendas.')
                    ->schema([
                        ViewEntry::make('errors.conflicts')
                            ->view('filament.admin.imports.store-conflicts'),
                    ])
                    ->visible(fn(Import $record): bool => $record->type === 'store' && !empty($record->errors['conflicts'] ?? null)),

                Section::make('Conflictos de condiciones SIM')
                    ->description('Aplica u omite los cambios detectados en la importaciÇün de condiciones SIM.')
                    ->schema([
                        ViewEntry::make('errors.conflicts')
                            ->view('filament.admin.imports.sales-conflicts'),
                    ])
                    ->visible(fn(Import $record): bool => $record->type === 'sales_condition' && !empty($record->errors['conflicts'] ?? null)),

                Section::make('⚠️ Error de Validación')
                    ->description(fn(Import $record) => $record->errors['message'] ?? 'Error de validación')
                    ->schema([
                        TextEntry::make('errors.message')
                            ->label('Problema')
                            ->badge()
                            ->color('danger')
                            ->size('lg')
                            ->columnSpanFull(),

                        \Filament\Infolists\Components\RepeatableEntry::make('errors.suggestions')
                            ->label('¿Cómo solucionarlo?')
                            ->schema([
                                TextEntry::make('.')
                                    ->label('')
                                    ->icon('heroicon-o-light-bulb')
                                    ->iconColor('warning')
                                    ->formatStateUsing(fn(string $state): string => $state),
                            ])
                            ->columnSpanFull()
                            ->visible(fn(Import $record): bool => !empty($record->errors['suggestions'] ?? null)),

                        TextEntry::make('errors.missing_columns_translated')
                            ->label('Columnas Faltantes')
                            ->badge()
                            ->color('danger')
                            ->formatStateUsing(fn(array $state): string => implode(', ', $state))
                            ->visible(fn(Import $record): bool => !empty($record->errors['missing_columns_translated'] ?? null)),

                        TextEntry::make('errors.available_columns')
                            ->label('Columnas Encontradas en el Archivo')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn(array $state): string => implode(', ', array_slice($state, 0, 10)))
                            ->visible(fn(Import $record): bool => !empty($record->errors['available_columns'] ?? null)),
                    ])
                    ->columns(1)
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(
                        fn(Import $record): bool =>
                        $record->status === 'failed' &&
                        ($record->errors['type'] ?? null) === 'validation_error'
                    ),

                Section::make('❌ Error del Sistema')
                    ->description('Ocurrió un error inesperado')
                    ->schema([
                        TextEntry::make('errors.message')
                            ->label('Mensaje')
                            ->badge()
                            ->color('danger')
                            ->columnSpanFull(),

                        TextEntry::make('errors.technical_details')
                            ->label('Detalles Técnicos')
                            ->markdown()
                            ->columnSpanFull()
                            ->visible(fn(Import $record): bool => !empty($record->errors['technical_details'] ?? null)),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('danger')
                    ->visible(
                        fn(Import $record): bool =>
                        $record->status === 'failed' &&
                        ($record->errors['type'] ?? null) === 'system_error'
                    ),

                Section::make('Errores del Sistema')
                    ->description('Detalles técnicos del error')
                    ->schema([
                        TextEntry::make('errors')
                            ->label('Error')
                            ->formatStateUsing(function (string|array|null $state): ?string {
                                if (empty($state)) {
                                    return 'Sin errores';
                                }

                                if (is_string($state)) {
                                    $decoded = json_decode($state, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $state = $decoded;
                                    } else {
                                        return $state;
                                    }
                                }

                                // Filtrar información ya mostrada
                                if (isset($state['duplicates'])) {
                                    unset($state['duplicates']);
                                }
                                if (isset($state['skipped'])) {
                                    unset($state['skipped']);
                                }
                                if (isset($state['summary'])) {
                                    unset($state['summary']);
                                }

                                if (empty($state)) {
                                    return 'Sin errores adicionales';
                                }

                                // Si hay un mensaje, formatearlo mejor
                                if (isset($state['message'])) {
                                    $message = $state['message'];

                                    // Separar "Columnas requeridas faltantes" de "Columnas disponibles"
                                    if (str_contains($message, 'Columnas requeridas faltantes:')) {
                                        $parts = explode('. Columnas disponibles:', $message);
                                        if (count($parts) === 2) {
                                            $missing = trim($parts[0]);
                                            $available = trim($parts[1]);

                                            // Formatear columnas disponibles en lista
                                            $availableColumns = array_map('trim', explode(',', $available));
                                            $formattedAvailable = implode("\n• ", $availableColumns);

                                            return "**{$missing}**\n\n**Columnas disponibles en el archivo:**\n• {$formattedAvailable}";
                                        }
                                    }

                                    return $message;
                                }

                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(
                        fn(Import $record): bool =>
                        $record->status === 'failed' &&
                        !in_array($record->errors['type'] ?? null, ['validation_error', 'system_error'])
                    ),
            ]);
    }

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
        // No decrementamos el contador de conflictos para mantener el historial


        $this->record->errors = $errors;
        $this->record->save();
        $this->record->refresh();

        Notification::make()
            ->title($action === 'update' ? 'Aplicado' : 'Omitido')
            ->body($action === 'update' ? 'Se aplicaron los cambios del conflicto.' : 'Se omitió el conflicto.')
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
            // Solo procesar STORE_CONFLICTS que NO estén ya resueltos
            if (($conflict['type'] ?? '') === 'store_conflict' && ($conflict['status'] ?? 'pending') !== 'resolved') {

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
                ->body("No habían conflictos pendientes de tiendas.")
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
                // noop
                break;
        }
    }

    protected function applyUserConflict(array $conflict): void
    {
        $doc = $conflict['id_number'] ?? null;
        if (!$doc) {
            return;
        }

        $user = User::where('id_number', $doc)->first();
        if (!$user) {
            return;
        }

        $incoming = $conflict['incoming'] ?? [];

        $user->update([
            'first_name' => $incoming['first_name'] ?? $user->first_name,
            'last_name' => $incoming['last_name'] ?? $user->last_name,
            'email' => $incoming['email'] ?? $user->email,
            'phone' => $incoming['phone'] ?? $user->phone,
        ]);

        if (!empty($conflict['idpos'])) {
            $store = Store::where('idpos', $conflict['idpos'])->first();
            if ($store) {
                $store->update([
                    'user_id' => $user->id,
                    'status' => StoreStatus::ACTIVE,
                ]);
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
        if (!$store) {
            return;
        }

        $incoming = $conflict['incoming'] ?? [];
        $updates = [];

        if (!empty($incoming['name'])) {
            $updates['name'] = $incoming['name'];
        }
        if (!empty($incoming['category'])) {
            $updates['category'] = $this->parseCategory($incoming['category']);
        }
        if (!empty($incoming['municipality'])) {
            $updates['municipality'] = $this->parseMunicipality($incoming['municipality']);
        }

        if (!empty($updates)) {
            $store->update($updates);
        }
    }

    public function resolveSalesConflictFromImport(int $index, string $action): void
    {
        $errors = $this->record->errors ?? [];
        $conflicts = $errors['conflicts'] ?? [];

        if (!isset($conflicts[$index])) {
            return;
        }

        $conflict = $conflicts[$index];

        if ($action === 'update') {
            $this->applySalesConflict($conflict);
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
            ->success()
            ->body($action === 'update' ? 'Se aplicaron los cambios del conflicto.' : 'Se omitiÇü el conflicto.')
            ->send();
    }

    protected function applySalesConflict(array $conflict): void
    {
        $iccid = $conflict['iccid'] ?? null;
        $incoming = $conflict['incoming'] ?? [];
        if (!$iccid || empty($incoming['period_date'])) {
            return;
        }

        $periodDate = Carbon::parse($incoming['period_date']);
        $year = (int) $periodDate->format('Y');
        $month = (int) $periodDate->format('m');

        $condition = SalesCondition::where('iccid', $iccid)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$condition) {
            return;
        }

        $condition->update([
            'phone_number' => $incoming['phone_number'] ?? $condition->phone_number,
            'idpos' => $incoming['idpos'] ?? $condition->idpos,
            'sale_price' => $incoming['sale_price'] ?? $condition->sale_price,
            'commission_percentage' => $incoming['commission_percentage'] ?? $condition->commission_percentage,
            'period_date' => $incoming['period_date'] ?? $condition->period_date,
        ]);
    }

    protected function parseCategory(string $value): ?StoreCategory
    {
        $upper = strtoupper(trim($value));
        foreach (StoreCategory::cases() as $case) {
            if (strtoupper($case->value) === $upper || strtoupper($case->label()) === $upper) {
                return $case;
            }
        }
        return null;
    }

    protected function parseMunicipality(string $value): ?Municipality
    {
        $upper = strtoupper(trim($value));
        foreach (Municipality::cases() as $case) {
            if (strtoupper($case->value) === $upper || strtoupper($case->label()) === $upper) {
                return $case;
            }
            if (strtoupper(str_replace('_', ' ', $case->value)) === $upper) {
                return $case;
            }
        }
        return null;
    }
}
