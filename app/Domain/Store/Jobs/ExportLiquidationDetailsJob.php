<?php

namespace App\Domain\Store\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Storage;
use App\Domain\User\Models\User;

class ExportLiquidationDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $failOnTimeout = true;

    protected int $userId;
    protected string $period;
    protected ?string $search;
    protected ?string $sort;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $period, ?string $search = null, ?string $sort = null)
    {
        $this->userId = $userId;
        $this->period = $period;
        $this->search = $search;
        $this->sort = $sort;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Unique Cache Key for this job
        $cacheKey = 'export_job_' . $this->userId . '_' . $this->period;
        Cache::put($cacheKey, 'PROCESSING', 600); // Mark as processing

        try {
            // 1. Retrieve Data from Cache
            $key = 'preview_data_' . $this->userId . '_' . $this->period;
            $data = Cache::get($key);

            if (!$data) {
                Cache::put($cacheKey, 'ERROR|No hay datos en caché', 600);
                return;
            }

            // Handle new cache structure (stores + lines) vs old structure (just stores)
            $storesData = is_array($data) && isset($data['stores']) ? $data['stores'] : $data;

            $allStores = collect($storesData);

            // 2. Apply Filters
            if ($this->search) {
                $search = strtolower($this->search);
                $allStores = $allStores->filter(
                    fn($s) =>
                    str_contains(strtolower($s['name']), $search) ||
                    str_contains((string) $s['idpos'], $search)
                );
            }

            // 3. Apply Sort
            if ($this->sort) {
                $allStores = match ($this->sort) {
                    'name_asc' => $allStores->sortBy('name'),
                    'name_desc' => $allStores->sortByDesc('name'),
                    'total_desc' => $allStores->sortByDesc('total'),
                    'total_asc' => $allStores->sortBy('total'),
                    'idpos_asc' => $allStores->sortBy('idpos'),
                    'idpos_desc' => $allStores->sortByDesc('idpos'),
                    default => $allStores,
                };
            }

            // 4. Generate CSV
            $fileName = 'liquidacion-detalle-' . $this->period . '-' . time() . '.csv';
            // Use 'public' disk so it's accessible via URL for download
            // Ensure 'storage/app/public' is symlinked!
            $filePath = 'exports/' . $fileName;

            Storage::disk('public')->makeDirectory('exports');
            $absPath = Storage::disk('public')->path($filePath);

            $handle = fopen($absPath, 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM

            // Headers
            fputcsv($handle, ['CÓDIGO TIENDA', 'NOMBRE TIENDA', 'ICCID', 'TELÉFONO', '% COMISIÓN', 'RECARGA MOVILCO', 'BASE LIQUIDACIÓN', 'TOTAL A PAGAR']);

            foreach ($allStores as $store) {
                if (empty($store['lines']))
                    continue;

                foreach ($store['lines'] as $line) {
                    fputcsv($handle, [
                        $store['idpos'],
                        $store['name'],
                        $line['iccid'] ?? 'N/D',
                        $line['phone_number'] ?? 'N/D',
                        number_format($line['residual_percentage'], 2) . '%',
                        $line['movilco_recharge_amount'],
                        $line['base_liquidation_final'],
                        $line['pago_residual'],
                    ]);
                }
            }

            fclose($handle);

            // 5. Mark as DONE and save URL
            $url = Storage::disk('public')->url($filePath);
            Cache::put($cacheKey, 'DONE|' . $url, 1200); // Valid for 20 mins

        } catch (\Exception $e) {
            Cache::put($cacheKey, 'ERROR|' . $e->getMessage(), 600);
            \Illuminate\Support\Facades\Log::error("Export failed: " . $e->getMessage());
        }
    }
}
