<?php

declare(strict_types=1);

namespace App\Domain\Admin\Filament\Resources\ImportResource\Pages;

use App\Domain\Admin\Filament\Resources\ImportResource;
use App\Domain\Import\Enums\ImportStatus;
use App\Domain\Import\Enums\ImportType;
use App\Domain\Import\Jobs\ProcessOperatorReportImportJob;
use App\Domain\Import\Jobs\ProcessRechargeImportJob;
use App\Domain\Import\Jobs\ProcessRedemptionProductImportJob;
use App\Domain\Import\Jobs\ProcessSalesConditionImportJob;
use App\Domain\Import\Jobs\ProcessStoreImportJob;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Services\ImportTypeDetectorService;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    protected bool $dispatchedImportJobs = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Importar')
                ->requiresConfirmation()
                ->modalHeading('Confirmar Importación')
                ->modalDescription(function (Get $get): string {
                    $type = $get('type') ?? 'desconocido';
                    $period = $get('period') ?? 'sin período';
                    $files = $get('files');
                    $fileCount = is_array($files) ? count($files) : 0;

                    $typeLabel = $this->getTypeLabel($type);

                    $message = "¿Estás seguro de importar {$fileCount} archivo(s) de tipo **{$typeLabel}** para el período **{$period}**?";

                    // Informar sobre cargas parciales
                    if ($type === ImportType::OPERATOR_REPORT->value || $type === ImportType::RECHARGE->value) {
                        $message .= "\n\n💡 **Tip:** Puedes subir el mismo período en múltiples partes. Los registros se agregarán sin sobrescribir datos existentes.";
                    }

                    return $message;
                })
                ->modalSubmitActionLabel('Sí, importar')
                ->modalCancelActionLabel('Cancelar'),
            $this->getCancelFormAction(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        // El formulario siempre envía 'files' como array (incluso con 1 archivo)
        if (isset($data['files']) && is_array($data['files']) && count($data['files']) > 0) {
            $batchId = Str::uuid()->toString();
            $createdBy = auth('admin')->id();
            $imports = [];
            $period = $data['period'] ?? null;
            $cutoffNumber = (int) ($data['cutoff_number'] ?? 0);
            $selectedType = $data['type'] ?? null;

            if (!$selectedType) {
                throw ValidationException::withMessages([
                    'type' => 'Selecciona el tipo de archivo que vas a subir.',
                ]);
            }

            $allowed = [
                ImportType::OPERATOR_REPORT->value,
                ImportType::RECHARGE->value,
            ];

            if (!in_array($selectedType, $allowed, true)) {
                throw ValidationException::withMessages([
                    'type' => 'Este módulo solo permite Pagos Claro o Recargas.',
                ]);
            }

            foreach ($data['files'] as $file) {
                try {
                    $filePath = Storage::disk('local')->path($file);
                    $detectedType = ImportTypeDetectorService::detect($filePath);

                    Log::info('CreateImport: handleRecordCreation - ANTES de crear Import', [
                        'file' => $file,
                        'filePath' => $filePath,
                        'detectedType' => $detectedType,
                        'batchId' => $batchId,
                        'createdBy' => $createdBy,
                    ]);

                    $description = $data['description'] ?? null;
                    if (!$description) {
                        $description = $this->getTypeLabel($selectedType);
                    }

                    /*
                    if ($detectedType && $detectedType !== $selectedType) {
                        Notification::make()
                            ->danger()
                            ->title('Tipo de archivo no coincide')
                            ->body('Seleccionaste "' . $this->getTypeLabel($selectedType) . '" pero el archivo parece ser ' . $this->getTypeLabel($detectedType) . '.')
                            ->send();
                        continue;
                    }
                    */

                    $cutoffForImport = $selectedType === ImportType::OPERATOR_REPORT->value ? $cutoffNumber : 0;

                    // Permitir múltiples importaciones del mismo período para cargas parciales
                    // Los registros se agregarán sin sobrescribir datos existentes

                    $import = Import::create([
                        'file' => $file,
                        'type' => $selectedType,
                        'status' => 'pending',
                        'batch_id' => $batchId,
                        'created_by' => $createdBy,
                        'description' => $description,
                        'period' => $period,
                        'cutoff_number' => $cutoffForImport,
                    ]);

                    Log::info('CreateImport: handleRecordCreation - Después de crear Import', [
                        'import_id' => $import->id,
                        'import_file' => $import->file,
                        'import_type' => $import->type,
                        'import_status' => $import->status,
                        'import_batch_id' => $import->batch_id,
                    ]);

                    $imports[] = $import;

                    $importId = $import->id;
                    Log::info('CreateImport: handleRecordCreation - Disparando job', [
                        'import_id' => $importId,
                        'type' => $selectedType,
                    ]);

                    // Intentar aumentar la memoria temporalmente para evitar fallos en archivos grandes
                    @ini_set('memory_limit', '2048M');
                    @set_time_limit(300);

                    $this->dispatchImportJob($selectedType, $importId);
                    $this->dispatchedImportJobs = true;
                } catch (\Throwable $e) {
                    Log::error('CreateImport: handleRecordCreation - ERROR al crear Import', [
                        'file' => $file,
                        'error_message' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('Falló la importación')
                        ->body($this->formatUploadError($e))
                        ->persistent()
                        ->send();
                    continue;
                }
            }

            if (empty($imports)) {
                Notification::make()
                    ->danger()
                    ->title('Ningún archivo pudo procesarse')
                    ->body('Verifica que los archivos no estén duplicados y que correspondan al tipo seleccionado.')
                    ->send();

                throw ValidationException::withMessages([
                    'files' => 'Ningún archivo pudo importarse. Revisa los mensajes anteriores y corrige los archivos.',
                ]);
            }

            $this->record = $imports[0];

            $successMessage = 'Se programaron ' . count($imports) . ' archivo(s) del tipo ' . $this->getTypeLabel($selectedType) . '. Los archivos se procesarán en breve.';

            if ($selectedType === ImportType::OPERATOR_REPORT->value || $selectedType === ImportType::RECHARGE->value) {
                $successMessage .= ' Puedes subir más archivos del mismo período si lo necesitas.';
            }

            Notification::make()
                ->success()
                ->title('Tanda de importación creada')
                ->body($successMessage)
                ->send();

            return $imports[0];
        }

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        /** @var Import $import */
        $import = $this->record;

        if (isset($import->batch_id) || $this->dispatchedImportJobs) {
            return;
        }

        $this->dispatchImportJob($import->type, $import->id);

        Notification::make()
            ->success()
            ->title('Importación creada')
            ->body("Tipo detectado: {$this->getTypeLabel($import->type)}. El archivo se procesará en breve.")
            ->send();
    }

    private function dispatchImportJob(string $type, int $importId): void
    {
        match ($type) {
            ImportType::OPERATOR_REPORT->value => ProcessOperatorReportImportJob::dispatch($importId),
            ImportType::RECHARGE->value => ProcessRechargeImportJob::dispatch($importId),
            ImportType::SALES_CONDITION->value => ProcessSalesConditionImportJob::dispatch($importId),
            ImportType::STORE->value => ProcessStoreImportJob::dispatch($importId),
            ImportType::REDEMPTION_PRODUCT->value => ProcessRedemptionProductImportJob::dispatch($importId),
            default => null,
        };
    }

    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            ImportType::OPERATOR_REPORT->value => 'Reporte del Operador',
            ImportType::RECHARGE->value => 'Recargas Variables',
            ImportType::SALES_CONDITION->value => 'Condiciones de Venta',
            ImportType::STORE->value => 'Tiendas',
            ImportType::REDEMPTION_PRODUCT->value => 'Productos redimibles',
            default => $type,
        };
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cutoff_number'] = (int) ($data['cutoff_number'] ?? 0);
        return $data;
    }

    private function formatUploadError(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Detectar errores de memoria comunes (si la excepción es capturable)
        if (
            str_contains(strtolower($message), 'allowed memory size') ||
            str_contains(strtolower($message), 'exhausted') ||
            str_contains(strtolower($message), 'out of memory')
        ) {
            return "⚠️ ERROR DE MEMORIA: El archivo es demasiado grande para este equipo.\n\n" .
                "Solución: Divide el archivo en partes más pequeñas (ej: 50,000 filas) e impórtalas una por una.";
        }

        if (
            str_contains(strtolower($message), 'execution time') ||
            str_contains(strtolower($message), 'time limit')
        ) {
            return "⚠️ TIEMPO DE ESPERA AGOTADO: El archivo tardó demasiado en procesarse.\n\n" .
                "Solución: Divide el archivo en partes más pequeñas.";
        }

        if (str_contains($message, 'No se pudo detectar')) {
            return 'No se reconoce el formato del archivo. Descarga la plantilla y verifica los encabezados.';
        }

        return 'Error técnico: ' . $message;
    }
}
