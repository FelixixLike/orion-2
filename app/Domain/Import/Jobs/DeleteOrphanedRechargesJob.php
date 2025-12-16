<?php

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Enums\ImportStatus;
use App\Domain\Admin\Models\BackgroundProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

class DeleteOrphanedRechargesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos maximo

    public function __construct(
        protected string $period,
        protected ?int $userId,
        protected int $backgroundProcessId
    ) {
    }

    public function handle(): void
    {
        $process = BackgroundProcess::find($this->backgroundProcessId);

        if (!$process) {
            Log::error("BackgroundProcess {$this->backgroundProcessId} not found");
            return;
        }

        try {
            Log::info("Iniciando eliminación masiva de recargas huérfanas para el periodo: {$this->period}");

            [$year, $month] = explode('-', $this->period);
            $year = (int) $year;
            $month = (int) $month;

            // 1. Obtener IDs válidos (Simcards que SÍ tienen reporte)
            $activeSimcardIds = OperatorReport::query()
                ->whereHas('import', function ($q) {
                    $q->where('type', 'operator_report')
                        ->where('status', ImportStatus::COMPLETED->value);
                })
                ->where(function ($q) {
                    $q->where('period_label', $this->period)
                        ->orWhereHas('import', function ($sq) {
                            $sq->where('period', $this->period);
                        });
                })
                ->distinct()
                ->pluck('simcard_id')
                ->toArray();

            // 2. Query base de eliminacion
            $queryBuilder = Recharge::query()
                ->where(function ($query) use ($year, $month) {
                    $query->where('period_label', $this->period)
                        ->orWhere(function ($sub) use ($year, $month) {
                            $sub->whereYear('period_date', $year)
                                ->whereMonth('period_date', $month);
                        });
                })
                ->whereIntegerNotInRaw('simcard_id', $activeSimcardIds);

            // Contar total para el progreso
            $totalToDelete = $queryBuilder->count();

            $process->update([
                'total' => $totalToDelete,
                'progress' => 0,
                'status' => 'running',
            ]);

            $totalDeleted = 0;

            // 3. Borrado por lotes (Chunked Delete) para proteger la DB
            do {
                $chunkIds = $queryBuilder->limit(2000)->pluck('id')->toArray();

                if (empty($chunkIds)) {
                    break;
                }

                $deleted = Recharge::whereIn('id', $chunkIds)->delete();
                $totalDeleted += $deleted;

                // Actualizar progreso
                $process->update(['progress' => $totalDeleted]);

            } while (!empty($chunkIds));

            Log::info("Finalizada eliminación huérfanas. Total eliminados: {$totalDeleted}");

            // Marcar como completado
            $process->update([
                'status' => 'completed',
                'progress' => $totalDeleted,
            ]);

            // Notificar al usuario
            $recipient = \App\Domain\User\Models\User::find($this->userId);
            if ($recipient) {
                Notification::make()
                    ->title('Eliminación completada')
                    ->body("Se han eliminado correctamente <strong>{$totalDeleted}</strong> recargas huérfanas del periodo {$this->period}.")
                    ->success()
                    ->sendToDatabase($recipient);
            }

        } catch (\Exception $e) {
            Log::error("Error eliminando recargas huérfanas: " . $e->getMessage());

            $process->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $recipient = \App\Domain\User\Models\User::find($this->userId);
            if ($recipient) {
                Notification::make()
                    ->title('Error en eliminación')
                    ->body("Ocurrió un error al eliminar las recargas: {$e->getMessage()}")
                    ->danger()
                    ->sendToDatabase($recipient);
            }
        }
    }
}
