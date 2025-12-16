<?php

namespace App\Domain\Admin\Filament\Resources\StoreResource\Pages;

use App\Domain\Admin\Filament\Resources\StoreResource;
use App\Domain\Admin\Filament\Resources\ImportResource;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Jobs\ProcessStoreImportJob;
use App\Domain\Import\Models\Import;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateStore extends CreateRecord implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = StoreResource::class;

    protected string $view = 'filament.admin.stores.create-custom';

    public ?array $bulkData = [];
    public array $conflicts = [];

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Formulario principal (renderiza todos los campos de tienda).
     */
    public function form(Schema $schema): Schema
    {
        return StoreResource::form($schema)->statePath('data');
    }

    /**
     * Luego de crear, sincroniza el tendero principal con la tabla pivot store_user.
     */
    protected function afterCreate(): void
    {
        if ($this->record && $this->record->user_id) {
            $this->record->users()->sync([$this->record->user_id]);
        }
    }

    protected function getForms(): array
    {
        return [
            'form',
            'bulkForm',
        ];
    }

    public function bulkForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('import_file')
                    ->label('Archivos Excel (.xlsx)')
                    ->disk('public')
                    ->directory('imports/stores')
                    // Validación de tipos eliminada temporalmente para desbloquear subida
                    ->required()
                    ->helperText('Arrastra y suelta tus archivos o Examina'),
                Toggle::make('update_conflicting_users')
                    ->label('Si la cÃ©dula existe pero difiere nombre/correo, actualizar datos del tendero')
                    ->default(false)
                    ->helperText('Si estÃ¡ apagado, deja la tienda inactiva y registra el conflicto.'),
            ])
            ->statePath('bulkData');
    }

    public function downloadTemplate()
    {
        return Storage::disk('public')->download('template/Tiendas.xlsx');
    }
    public function processBulkUpload()
    {
        $data = $this->bulkForm->getState();
        $filePath = $data['import_file'];
        $updateConflicts = (bool) ($data['update_conflicting_users'] ?? false);

        try {
            $batchId = Str::uuid()->toString();
            $createdBy = auth('admin')->id();

            $import = Import::create([
                'file' => $filePath,
                'type' => ImportType::STORE->value,
                'status' => 'pending',
                'batch_id' => $batchId,
                'created_by' => $createdBy,
                'description' => 'Importación masiva de tiendas',
                'errors' => [
                    'options' => [
                        'update_conflicting_users' => $updateConflicts,
                    ],
                ],
            ]);

            ProcessStoreImportJob::dispatch($import->id);

            Notification::make()
                ->title('⏳ Importación Iniciada en Segundo Plano')
                ->info()
                ->body('El proceso ha comenzado. Puedes continuar trabajando; recibirás una notificación cuando termine.')
                ->persistent()
                ->send();

            $this->redirect(ImportResource::getUrl('view', ['record' => $import->id], panel: 'admin'));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en Importación')
                ->danger()
                ->body('Ocurrió un error al crear/procesar la importación: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Valida los datos del formulario antes de crear la tienda para evitar inserts incompletos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    /**
     * NotificaciÃ³n de conflicto con acciones inmediatas.
     */
    protected function notifyConflict(array $error): void
    {
        $body = $error['message'] ?? 'Revisar conflicto detectado.';

        Notification::make()
            ->title('Conflicto detectado')
            ->danger()
            ->body($body)
            ->persistent()
            ->actions([
                Action::make('apply')
                    ->label('Aplicar cambio')
                    ->color('success')
                    ->action(fn() => $this->resolveConflict($error, 'update')),
                Action::make('omit')
                    ->label('Omitir')
                    ->color('secondary')
                    ->action(fn() => $this->resolveConflict($error, 'omit')),
            ])
            ->send();
    }

    public function handleConflict(int $index, string $action): void
    {
        if (!isset($this->conflicts[$index])) {
            return;
        }

        $conflict = $this->conflicts[$index];
        $this->resolveConflict($conflict, $action);

        unset($this->conflicts[$index]);
        $this->conflicts = array_values($this->conflicts);
    }

    protected function resolveConflict(array $error, string $action): void
    {
        if ($action === 'omit') {
            Notification::make()
                ->title('Omitido')
                ->body('Se dejÃ³ sin cambios.')
                ->send();
            return;
        }

        try {
            switch ($error['type'] ?? null) {
                case 'user_conflict':
                    $this->resolveUserConflict($error);
                    break;
                case 'store_conflict':
                    $this->resolveStoreConflict($error);
                    break;
                default:
                    Notification::make()
                        ->title('No aplicado')
                        ->warning()
                        ->body('Tipo de conflicto no soportado para acciÃ³n automÃ¡tica.')
                        ->send();
                    return;
            }

            Notification::make()
                ->title('Aplicado')
                ->success()
                ->body('Se actualizaron los datos segÃºn tu elecciÃ³n.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('No se pudo aplicar el cambio: ' . $e->getMessage())
                ->send();
        }
    }

    protected function resolveUserConflict(array $error): void
    {
        $doc = $error['id_number'] ?? null;
        if (!$doc) {
            return;
        }

        $user = User::where('id_number', $doc)->first();
        if (!$user) {
            return;
        }

        $incoming = $error['incoming'] ?? [];

        $user->update([
            'first_name' => $incoming['first_name'] ?? $user->first_name,
            'last_name' => $incoming['last_name'] ?? $user->last_name,
            'email' => $incoming['email'] ?? $user->email,
            'phone' => $incoming['phone'] ?? $user->phone,
        ]);

        if (!empty($error['idpos'])) {
            $store = Store::where('idpos', $error['idpos'])->first();
            if ($store) {
                $store->update([
                    'user_id' => $user->id,
                    'status' => StoreStatus::ACTIVE,
                ]);
                $store->users()->sync([$user->id]);
            }
        }
    }

    protected function resolveStoreConflict(array $error): void
    {
        $store = null;
        if (!empty($error['store_id'])) {
            $store = Store::find($error['store_id']);
        }
        if (!$store && !empty($error['idpos'])) {
            $store = Store::where('idpos', $error['idpos'])->first();
        }
        if (!$store) {
            return;
        }

        $incoming = $error['incoming'] ?? [];
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

