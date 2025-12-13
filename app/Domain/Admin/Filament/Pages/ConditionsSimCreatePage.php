<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Import\Enums\ImportStatus;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Services\IccidCleanerService;
use App\Domain\Import\Services\SimcardService;
use App\Domain\Store\Models\Store;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConditionsSimCreatePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.conditions-simcard.conditions-create';

    public ?string $iccid = null;
    public ?string $phone_number = null;
    public ?string $idpos = null;
    public ?string $sale_price = null;
    public ?string $commission_percentage = null;
    public ?string $period_date = null;
    public ?string $population = null;

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'conditions-sim/create';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::guard('admin')->user()?->can('sales_conditions.view') ?? false;
    }

    public function mount(): void
    {
        $this->period_date = now()->startOfMonth()->toDateString();
    }

    public function getModuleUrl(): string
    {
        return ConditionsSimPage::getUrl(panel: 'admin');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('iccid')
                ->label('ICCID')
                ->required()
                ->maxLength(50)
                ->placeholder('8957...')
                ->helperText('Debe coincidir con la SIM a liquidar.'),
            TextInput::make('phone_number')
                ->label('NUMERODETELEFONO')
                ->maxLength(20)
                ->placeholder('Opcional'),
            Select::make('idpos')
                ->label('IDPOS')
                ->required()
                ->options(function (): array {
                    return Store::query()
                        ->whereNotNull('idpos')
                        ->orderBy('idpos')
                        ->get()
                        ->mapWithKeys(fn(Store $store) => [
                            $store->idpos => $store->idpos,
                        ])
                        ->all();
                })
                ->searchable()
                ->helperText('ID del punto de venta (tienda).'),
            TextInput::make('sale_price')
                ->label('VALOR')
                ->required()
                ->numeric()
                ->prefix('$')
                ->helperText('Valor SIM o base para residual.'),
            TextInput::make('commission_percentage')
                ->label('RESIDUAL %')
                ->required()
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText('Ingresa solo numero, sin simbolos.'),
            TextInput::make('population')
                ->label('POBLACION')
                ->maxLength(100)
                ->placeholder('Ej: Residual, Cambios, RC 936')
                ->columnSpanFull(),
            DatePicker::make('period_date')
                ->label('FECHA VENTA')
                ->required()
                ->maxDate(now())
                ->helperText('Fecha de venta / vigencia. No puede ser futura.'),
        ])->columns(2);
    }

    public function submit(): void
    {
        $iccid = trim((string) $this->iccid);

        if (!$iccid) {
            Notification::make()
                ->title('ICCID invalido')
                ->danger()
                ->body('El ICCID es obligatorio.')
                ->send();

            return;
        }

        $period = $this->period_date
            ? Carbon::parse($this->period_date)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $periodYear = (int) $period->format('Y');
        $periodMonth = (int) $period->format('m');

        $simcard = SimcardService::findOrCreateByIccid($iccid, $this->phone_number);
        $existingCondition = $simcard
            ? SalesCondition::query()
                ->where('simcard_id', $simcard->id)
                ->where('period_year', $periodYear)
                ->where('period_month', $periodMonth)
                ->first()
            : null;

        if (!$simcard) {
            Notification::make()
                ->title('Simcard no encontrada')
                ->danger()
                ->body('No se encontro una SIM con ese ICCID limpio.')
                ->send();

            return;
        }

        $data = Validator::make(
            [
                'iccid' => $iccid,
                'phone_number' => $this->phone_number,
                'idpos' => $this->idpos,
                'sale_price' => $this->sale_price,
                'commission_percentage' => $this->commission_percentage,
                'population' => $this->population,
                'period_date' => $period->toDateString(),
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'simcard_id' => $simcard->id,
            ],
            [
                'iccid' => ['required', 'string', 'max:50'],
                'phone_number' => ['nullable', 'string', 'max:20'],
                'idpos' => ['required', 'string', 'max:20', Rule::exists('stores', 'idpos')],
                'sale_price' => ['required', 'numeric'],
                'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
                'population' => ['nullable', 'string', 'max:100'],
                'period_date' => ['required', 'date', 'before_or_equal:today'],
                'period_year' => ['required', 'integer'],
                'period_month' => ['required', 'integer'],
                'simcard_id' => [
                    'required',
                    Rule::unique('sales_conditions')
                        ->where(fn($query) => $query
                            ->where('period_year', $periodYear)
                            ->where('period_month', $periodMonth))
                        ->ignore($existingCondition?->id),
                ],
            ]
        )->validate();

        $createdBy = $existingCondition?->created_by ?? Auth::guard('admin')->id();

        SalesCondition::updateOrCreate(
            [
                'simcard_id' => $simcard->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ],
            [
                'iccid' => $iccid,
                'phone_number' => $data['phone_number'] ?? null,
                'idpos' => $data['idpos'],
                'sale_price' => $data['sale_price'],
                'commission_percentage' => $data['commission_percentage'],
                'population' => $data['population'] ?? null,
                'period_date' => $period->toDateString(),
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'created_by' => $createdBy,
            ]
        );

        Notification::make()
            ->title('Condicion creada')
            ->success()
            ->send();

        $this->redirect(ConditionsSimListPage::getUrl(panel: 'admin'));
    }
    public ?array $bulkData = [];

    public function downloadTemplate()
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->download('template/Condiciones_Simcard.xlsx');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(Schema::make($this)),
            'bulkForm' => Schema::make($this)
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Archivos Excel (.xlsx)')
                        ->helperText('Arrastra y suelta tus archivos o Examina')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('imports/sales-conditions')
                        ->maxSize(10240)
                        ->uploadingMessage('Subiendo archivo...')
                        ->required(),
                ])
                ->statePath('bulkData'),
        ];
    }

    public function processBulkUpload(): void
    {
        $data = $this->bulkForm->getState();
        $filePath = $data['file'];
        $period = now()->format('Y-m');

        $import = Import::create([
            'type' => ImportType::SALES_CONDITION,
            'status' => ImportStatus::PROCESSING,
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'period' => $period,
            'created_by' => Auth::guard('admin')->id(),
        ]);

        try {
            $importer = new \App\Domain\Import\Imports\SalesConditionImport($import->id);
            $importer->import($filePath, 'local');

            $stats = $importer->getStats();
            $errors = $importer->getErrors();

            $import->update([
                'processed_rows' => $stats['total_processed'] ?? 0,
                'successful_rows' => ($stats['inserted'] ?? 0) + ($stats['updated'] ?? 0),
                'error_rows' => $stats['skipped'] ?? 0,
                'errors' => $errors,
                'status' => \App\Domain\Import\Enums\ImportStatus::COMPLETED,
            ]);

            Notification::make()
                ->title('Carga completada')
                ->success()
                ->body("Procesados: {$stats['total_processed']} | Insertados: {$stats['inserted']} | Actualizados: {$stats['updated']}")
                ->send();

            $this->bulkForm->fill();
            $this->bulkData = [];

        } catch (\Exception $e) {
            $import->update([
                'status' => \App\Domain\Import\Enums\ImportStatus::FAILED,
                'errors' => ['exception' => $e->getMessage()]
            ]);

            Notification::make()
                ->title('Error en la carga')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }
}
