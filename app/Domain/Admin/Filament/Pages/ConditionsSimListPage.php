<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Services\IccidCleanerService;
use App\Domain\Store\Models\Store;
use App\Domain\Admin\Exports\SalesConditionsExport;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select as FormsSelect;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ConditionsSimListPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.conditions-simcard.conditions-list';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'conditions-sim/list';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::guard('admin')->user()?->can('sales_conditions.view') ?? false;
    }

    public function getModuleUrl(): string
    {
        return ConditionsSimPage::getUrl(panel: 'admin');
    }

    public function getCreateUrl(): string
    {
        return ConditionsSimCreatePage::getUrl(panel: 'admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('period_year', 'desc')
            ->columns([
                TextColumn::make('iccid')
                    ->label('ICCID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('phone_number')
                    ->label('TELEFONO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('idpos')
                    ->label('IDPOS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label('VALOR')
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('commission_percentage')
                    ->label('RESIDUAL')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2) . '%')
                    ->sortable(),
                TextColumn::make('population')
                    ->label('POBLACION')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('period_date')
                    ->label('FECHA VENTA')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('creator', function (Builder $query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('idpos')
                    ->label('IDPOS')
                    ->options(fn() => SalesCondition::query()
                        ->select('idpos')
                        ->distinct()
                        ->orderBy('idpos')
                        ->pluck('idpos', 'idpos')
                        ->toArray()),
                Tables\Filters\Filter::make('residual_range')
                    ->form([
                        FormsSelect::make('range')
                            ->label('Rango residual')
                            ->options([
                                'lt3' => '< 3%',
                                '3to7' => '3% - 7%',
                                'gt7' => '> 7%',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['range'] === 'lt3', fn(Builder $q) => $q->where('commission_percentage', '<', 3))
                            ->when($data['range'] === '3to7', fn(Builder $q) => $q->whereBetween('commission_percentage', [3, 7]))
                            ->when($data['range'] === 'gt7', fn(Builder $q) => $q->where('commission_percentage', '>', 7));
                    }),
                Tables\Filters\Filter::make('period_range')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('to')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q, $date) => $q->whereDate('period_date', '>=', $date))
                            ->when($data['to'] ?? null, fn(Builder $q, $date) => $q->whereDate('period_date', '<=', $date));
                    }),
                Tables\Filters\Filter::make('store_status')
                    ->form([
                        FormsSelect::make('status')
                            ->label('Estado de Tienda')
                            ->options([
                                'with_store' => 'Con Tienda Asignada',
                                'no_store' => '⚠️ Sin Tienda (Huérfana)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['status'] === 'with_store', fn(Builder $q) => $q->whereExists(function ($sub) {
                                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                                    ->from('stores')
                                    ->whereColumn('stores.idpos', 'sales_conditions.idpos');
                            }))
                            ->when($data['status'] === 'no_store', fn(Builder $q) => $q->whereNotExists(function ($sub) {
                                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                                    ->from('stores')
                                    ->whereColumn('stores.idpos', 'sales_conditions.idpos');
                            }));
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de condicion')
                    ->modalDescription(fn($record) => 'ICCID: ' . ($record->iccid ?? '') . ' | IDPOS: ' . ($record->idpos ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn($record) => view('filament.admin.conditions-simcard.partials.condition-context', [
                        'condition' => $record,
                        'storeLabel' => $this->getStoreLabelByIdpos($record->idpos),
                        'tender' => $this->getTenderByIdpos($record->idpos),
                    ])),

                EditAction::make()
                    ->label('Editar')
                    ->form($this->getFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['iccid'] = $data['iccid'] ? trim((string) $data['iccid']) : null;

                        if (!empty($data['period_date'])) {
                            $period = Carbon::parse($data['period_date'])->startOfMonth();
                            $data['period_date'] = $period->toDateString();
                            $data['period_year'] = (int) $period->format('Y');
                            $data['period_month'] = (int) $period->format('m');
                        }

                        return $data;
                    })
                    ->using(function ($record, array $data) {
                        if ($record->simcard_id && isset($data['period_year'], $data['period_month'])) {
                            $conflict = SalesCondition::query()
                                ->where('simcard_id', $record->simcard_id)
                                ->where('period_year', $data['period_year'])
                                ->where('period_month', $data['period_month'])
                                ->where('id', '!=', $record->id)
                                ->exists();

                            if ($conflict) {
                                throw ValidationException::withMessages([
                                    'period_date' => 'Ya existe una condicion para esta SIM y periodo.',
                                ]);
                            }
                        }

                        $record->update($data);
                        return $record;
                    })
                    ->extraModalFooterActions(function ($record) {
                        return [
                            DeleteAction::make()
                                ->record($record)
                                ->requiresConfirmation()
                                ->modalHeading('¿Desea borrar esta simcard?')
                                ->modalDescription('Se eliminará la condición de venta para la ICCID: ' . $record->iccid)
                                ->cancelParentActions(),
                        ];
                    }),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        return SalesCondition::query();
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('iccid')
                ->label('ICCID')
                ->required()
                ->unique(table: SalesCondition::class, column: 'iccid', ignoreRecord: true)
                ->validationMessages(['unique' => 'El ICCID ya está duplicado.'])
                ->maxLength(50),
            \Filament\Forms\Components\TextInput::make('phone_number')
                ->label('Numero de telefono')
                ->maxLength(20),
            \Filament\Forms\Components\TextInput::make('idpos')
                ->label('IDPOS')
                ->required()
                ->maxLength(20),
            \Filament\Forms\Components\TextInput::make('sale_price')
                ->label('Valor')
                ->numeric()
                ->required()
                ->prefix('$'),
            \Filament\Forms\Components\TextInput::make('commission_percentage')
                ->label('Residual %')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->required()
                ->suffix('%'),
            \Filament\Forms\Components\TextInput::make('population')
                ->label('POBLACION')
                ->maxLength(100)
                ->columnSpanFull(),
            \Filament\Forms\Components\DatePicker::make('period_date')
                ->label('Fecha venta')
                ->required()
                ->maxDate(now()),
        ];
    }

    private array $storeCache = [];
    private array $tenderCache = [];

    private function getStoreIdByIdpos(?string $idpos): ?int
    {
        if (!$idpos) {
            return null;
        }

        if (!array_key_exists($idpos, $this->storeCache)) {
            $this->storeCache[$idpos] = Store::where('idpos', $idpos)->value('id');
        }

        return $this->storeCache[$idpos];
    }

    private function getStoreLabelByIdpos(?string $idpos): ?string
    {
        if (!$idpos) {
            return null;
        }

        $store = Store::query()
            ->select(['idpos', 'name', 'route_code', 'circuit_code'])
            ->where('idpos', $idpos)
            ->first();

        if (!$store) {
            return null;
        }

        $label = ($store->idpos ?? '') . ' - ' . ($store->name ?? '');
        if ($store->route_code) {
            $label .= ' / Ruta ' . $store->route_code;
        }
        if ($store->circuit_code) {
            $label .= ' / Circuito ' . $store->circuit_code;
        }

        return $label;
    }

    private function getTenderByIdpos(?string $idpos): ?array
    {
        if (!$idpos) {
            return null;
        }

        if (array_key_exists($idpos, $this->tenderCache)) {
            return $this->tenderCache[$idpos];
        }

        $store = Store::query()
            ->with([
                'users' => function ($q) {
                    $q->select('users.id', 'users.first_name', 'users.last_name', 'users.email');
                }
            ])
            ->where('idpos', $idpos)
            ->first();

        $tender = $store?->users?->first();

        return $this->tenderCache[$idpos] = $tender
            ? [
                'name' => trim(($tender->first_name ?? '') . ' ' . ($tender->last_name ?? '')),
                'email' => $tender->email,
            ]
            : null;
    }

    public function exportToExcel()
    {
        // Get all records from the current table query (respects filters and search)
        $records = $this->getFilteredTableQuery()->get();

        // Create filename with timestamp
        $filename = 'condiciones_sim_' . now()->format('Y-m-d_His') . '.xlsx';

        // Show notification
        Notification::make()
            ->success()
            ->title('Exportación exitosa')
            ->body("Se exportaron {$records->count()} registros a Excel")
            ->send();

        // Return the download response
        return Excel::download(
            new SalesConditionsExport($records),
            $filename
        );
    }
}
