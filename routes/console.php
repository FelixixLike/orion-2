<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stores:resolve-conflicts {file} {--action=omit : update|omit} {--dry-run}', function (string $file) {
    $action = $this->option('action') ?? 'omit';
    $dryRun = (bool) $this->option('dry-run');

    if (! file_exists($file)) {
        $this->error("No se encuentra el archivo: {$file}");
        return Command::FAILURE;
    }

    $content = file_get_contents($file);
    $data = json_decode($content, true);

    if (! is_array($data)) {
        $this->error('El archivo no es un JSON válido.');
        return Command::FAILURE;
    }

    $updatedUsers = 0;
    $updatedStores = 0;
    $skipped = 0;

    foreach ($data as $item) {
        $type = $item['type'] ?? 'unknown';

        if ($action === 'omit') {
            $skipped++;
            continue;
        }

        if ($type === 'user_conflict') {
            $doc = $item['id_number'] ?? null;
            $incoming = $item['incoming'] ?? [];
            if (! $doc) {
                $skipped++;
                continue;
            }
            $user = User::where('id_number', $doc)->first();
            if (! $user) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $updatedUsers++;
                continue;
            }
            $user->update([
                'first_name' => $incoming['first_name'] ?? $user->first_name,
                'last_name' => $incoming['last_name'] ?? $user->last_name,
                'email' => $incoming['email'] ?? $user->email,
                'phone' => $incoming['phone'] ?? $user->phone,
            ]);
            $updatedUsers++;
        } elseif ($type === 'store_conflict') {
            $idpos = $item['idpos'] ?? null;
            $incoming = $item['incoming'] ?? [];
            if (! $idpos) {
                $skipped++;
                continue;
            }
            $store = Store::where('idpos', $idpos)->first();
            if (! $store) {
                $skipped++;
                continue;
            }
            $name = $incoming['name'] ?? $store->name;
            $cat = $incoming['category'] ?? ($store->category?->value);
            $mun = $incoming['municipality'] ?? ($store->municipality?->value);

            $catEnum = $cat ? StoreCategory::tryFrom($cat) : $store->category;
            $munEnum = $mun ? Municipality::tryFrom($mun) : $store->municipality;

            if (! $dryRun) {
                $store->update([
                    'name' => $name,
                    'category' => $catEnum,
                    'municipality' => $munEnum,
                ]);
            }
            $updatedStores++;
        } else {
            $skipped++;
        }
    }

    $this->info("Acción: {$action} | Dry-run: " . ($dryRun ? 'sí' : 'no'));
    $this->info("Usuarios actualizados: {$updatedUsers}");
    $this->info("Tiendas actualizadas: {$updatedStores}");
    $this->info("Omitidos/otros: {$skipped}");

    return Command::SUCCESS;
})->describe('Resuelve conflictos de importación de tiendas desde un archivo JSON generado en la carga masiva.');
