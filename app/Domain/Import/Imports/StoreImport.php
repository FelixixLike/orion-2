<?php

namespace App\Domain\Import\Imports;

use App\Domain\Route\Models\Route as SalesRoute;
use App\Domain\Store\Models\Municipality;
use App\Domain\Store\Models\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use App\Domain\Import\Models\Import;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\BeforeImport;
use App\Domain\Import\Services\ImportProcessorService;
use Illuminate\Support\Facades\Log;

class StoreImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithChunkReading, WithEvents, ShouldQueue
{
    public int $chunkProcessedCount = 0;
    public int $chunkErrorCount = 0;
    public array $chunkErrors = [];

    public ?int $importId = null;
    public bool $updateConflictingUsers = false;
    public ?int $createdBy = null;

    // Cache local para rutas (evitar miles de queries repetitivas)
    public array $routeCache = [];
    public bool $routesLoaded = false;

    public function __construct(?int $importId = null, bool $updateConflictingUsers = false)
    {
        $this->importId = $importId;
        $this->updateConflictingUsers = $updateConflictingUsers;

        if ($importId) {
            $import = Import::find($importId);
            $this->createdBy = $import?->created_by;
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => [$this, 'beforeImport'],
            AfterChunk::class => [$this, 'afterChunk'],
            AfterImport::class => [$this, 'afterImport'],
        ];
    }

    public function beforeImport(BeforeImport $event)
    {
        try {
            $totalRows = 0;
            $allSheets = $event->getReader()->getTotalRows();

            if (!empty($allSheets)) {
                if (isset($allSheets['Tabla'])) {
                    $totalRows = max(0, $allSheets['Tabla'] - 1);
                } else {
                    $totalRows = max(0, reset($allSheets) - 1);
                }
            }

            if ($this->importId) {
                Import::where('id', $this->importId)->update([
                    'total_rows' => $totalRows,
                    'status' => 'processing',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("StoreImport BeforeImport Error: " . $e->getMessage());
        }
    }

    public function afterChunk(AfterChunk $event)
    {
        try {
            if ($this->importId) {
                if ($this->chunkProcessedCount > 0) {
                    Import::where('id', $this->importId)->increment('processed_rows', $this->chunkProcessedCount);
                }
                if ($this->chunkErrorCount > 0) {
                    Import::where('id', $this->importId)->increment('failed_rows', $this->chunkErrorCount);
                }

                if (!empty($this->chunkErrors)) {
                    $this->saveChunkErrors();
                }
            }

            $this->chunkProcessedCount = 0;
            $this->chunkErrorCount = 0;
            $this->chunkErrors = [];

        } catch (\Throwable $e) {
            Log::error("StoreImport AfterChunk Error: " . $e->getMessage());
        }
    }

    public function afterImport(AfterImport $event)
    {
        if ($this->importId) {
            ImportProcessorService::finalize($this->importId);
        }
    }

    public function collection(Collection $rows)
    {
        $this->loadRoutesCache();

        $idposList = [];
        $docsList = [];

        foreach ($rows as $row) {
            $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : $row;
            if (!empty($rowArray['id_pdv']))
                $idposList[] = (string) $rowArray['id_pdv'];

            $doc = $rowArray['nro_documento_cliente'] ?? $rowArray['documento'] ?? null;
            if ($doc)
                $docsList[] = (string) $doc;
        }

        $existingStores = Store::whereIn('idpos', $idposList)->get()->keyBy('idpos');
        $existingUsers = User::whereIn('id_number', $docsList)->get()->keyBy('id_number');

        foreach ($rows as $index => $row) {
            $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : $row;
            $this->chunkProcessedCount++;

            try {
                $this->processSingleRow($rowArray, $existingStores, $existingUsers, $index);
            } catch (\Throwable $e) {
                $this->chunkErrorCount++;
                $this->chunkErrors['details'][] = [
                    'type' => 'exception',
                    'row' => "IDPOS: " . ($rowArray['id_pdv'] ?? '?'),
                    'message' => $e->getMessage(),
                ];
            }
        }
    }

    private function processSingleRow($rowArray, $existingStores, $existingUsers, $loopIndex)
    {
        $idpos = isset($rowArray['id_pdv']) ? (string) $rowArray['id_pdv'] : null;
        $storeName = isset($rowArray['nombre_punto']) ? (string) $rowArray['nombre_punto'] : null;

        if (!$idpos || !$storeName)
            return;

        $doc = isset($rowArray['nro_documento_cliente']) ? (string) $rowArray['nro_documento_cliente'] : (
            isset($rowArray['documento']) ? (string) $rowArray['documento'] : null
        );

        $clientName = isset($rowArray['nombre_cliente']) ? (string) $rowArray['nombre_cliente'] : null;
        $clientLastName = isset($rowArray['apellido_cliente']) ? (string) $rowArray['apellido_cliente'] : null;
        $clientPhone = isset($rowArray['telefono_cliente']) ? (string) $rowArray['telefono_cliente'] : null;
        $clientEmail = isset($rowArray['correo_electronico_cliente']) ? (string) $rowArray['correo_electronico_cliente'] : null;

        $routeCode = isset($rowArray['ruta']) ? (string) $rowArray['ruta'] : null;
        $circuitCode = isset($rowArray['circuito']) ? (string) $rowArray['circuito'] : null;

        // Rutas cache local
        $this->ensureRoute($routeCode, 'route');
        $this->ensureRoute($circuitCode, 'circuit');

        $category = isset($rowArray['categoria']) ? (string) $rowArray['categoria'] : null;
        $storePhone = isset($rowArray['celular']) ? (string) $rowArray['celular'] : null;
        $municipality = isset($rowArray['municipio']) ? (string) $rowArray['municipio'] : null;
        $hood = isset($rowArray['barrio']) ? (string) $rowArray['barrio'] : null;
        $address = isset($rowArray['direccion']) ? (string) $rowArray['direccion'] : null;
        $storeEmail = isset($rowArray['correo_electronico']) ? (string) $rowArray['correo_electronico'] : null;

        $catName = $this->ensureCategory($category);
        $muniName = $this->ensureMunicipality($municipality);

        $user = null;

        if ($doc) {
            $existing = $existingUsers->get($doc);

            $firstName = trim($clientName ?? '');
            $lastName = trim($clientLastName ?? '');

            if (!$lastName && $firstName) {
                $parts = explode(' ', $firstName);
                if (count($parts) > 1) {
                    $lastName = array_pop($parts);
                    $firstName = implode(' ', $parts);
                }
            }

            if ($existing) {
                if ($this->updateConflictingUsers) {
                    $existing->update([
                        'first_name' => $firstName ?: $existing->first_name,
                        'last_name' => $lastName ?: $existing->last_name,
                        'phone' => $clientPhone ?: $existing->phone,
                    ]);
                    $user = $existing;
                } else {
                    $isDifferent = ($firstName && stripos($existing->first_name ?? '', $firstName) === false);
                    if ($isDifferent) {
                        $this->addConflict([
                            'type' => 'user_conflict',
                            'row' => "IDPOS: $idpos",
                            'idpos' => $idpos,
                            'id_number' => $doc,
                            'existing' => ['name' => $existing->first_name . ' ' . $existing->last_name],
                            'incoming' => ['name' => $firstName . ' ' . $lastName],
                            'message' => "Conflicto Usuario ($doc): Nombre diferente.",
                            'status' => 'pending',
                            'action' => 'pending'
                        ]);
                        $user = $existing;
                    } else {
                        $user = $existing;
                    }
                }
            } else {
                if ($firstName || $storeName) {
                    $username = $doc;
                    $email = $clientEmail ?: ($doc . '@placeholder.com');
                    try {
                        $user = User::create([
                            'first_name' => $firstName ?: 'Admin',
                            'last_name' => $lastName ?: $storeName,
                            'id_number' => $doc,
                            'phone' => $clientPhone,
                            'email' => $email,
                            'password_hash' => Hash::make(Str::random(12)),
                            'status' => 'inactive',
                            'username' => $username,
                        ]);
                        $user->assignRole('retailer');
                        $existingUsers->put($doc, $user);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        $store = $existingStores->get($idpos);

        $data = [
            'idpos' => $idpos,
            'name' => $storeName,
            'route_code' => $routeCode,
            'circuit_code' => $circuitCode,
            'category' => $catName,
            'phone' => $storePhone,
            'municipality' => $muniName,
            'neighborhood' => $hood,
            'address' => $address,
            'email' => $storeEmail,
            'user_id' => $user?->id,
            'status' => StoreStatus::ACTIVE,
        ];

        if ($store) {
            $storeConflict = false;
            $conflictFields = [];
            $existingData = [];
            $incomingData = [];

            // Detect conflicts
            if (strcasecmp((string) $store->name, (string) $storeName) !== 0) {
                $storeConflict = true;
                $conflictFields[] = 'Nombre';
                $existingData['name'] = $store->name;
                $incomingData['name'] = $storeName;
            }

            if ($catName && strcasecmp((string) $store->category, (string) $catName) !== 0) {
                $storeConflict = true;
                $conflictFields[] = 'Categoría';
                $existingData['category'] = $store->category;
                $incomingData['category'] = $catName;
            }

            if ($circuitCode && strcasecmp((string) $store->circuit_code, (string) $circuitCode) !== 0) {
                $storeConflict = true;
                $conflictFields[] = 'Circuito';
                $existingData['circuit'] = $store->circuit_code;
                $incomingData['circuit'] = $circuitCode;
            }

            if ($routeCode && strcasecmp((string) $store->route_code, (string) $routeCode) !== 0) {
                $storeConflict = true;
                $conflictFields[] = 'Ruta';
                $existingData['route'] = $store->route_code;
                $incomingData['route'] = $routeCode;
            }

            if ($storeConflict) {
                $conflictLabel = implode(', ', $conflictFields);
                $this->addConflict([
                    'type' => 'store_conflict',
                    'row' => "IDPOS: $idpos",
                    'idpos' => $idpos,
                    'store_id' => $store->id,
                    'existing' => $existingData,
                    'incoming' => $incomingData,
                    'message' => "Conflicto Tienda ($conflictLabel)",
                    'status' => 'pending',
                    'action' => 'pending'
                ]);
            } else {
                $store->update($data);
            }
            if ($user && $user->id)
                $store->users()->syncWithoutDetaching([$user->id]);
        } else {
            if ($this->createdBy)
                $data['created_by'] = $this->createdBy;
            $store = Store::create($data);
            if ($user && $user->id)
                $store->users()->syncWithoutDetaching([$user->id]);
            $existingStores->put($idpos, $store);
        }
    }

    private function addConflict(array $conflict)
    {
        $this->chunkErrorCount++;
        $this->chunkErrors['conflicts'][] = $conflict;

        if (!isset($this->chunkErrors['summary']['duplicates']))
            $this->chunkErrors['summary']['duplicates'] = 0;
        $this->chunkErrors['summary']['duplicates']++;
    }

    private function saveChunkErrors()
    {
        $import = Import::find($this->importId);
        $currentErrors = $import->errors ?? [];
        if (!is_array($currentErrors))
            $currentErrors = [];

        if (isset($this->chunkErrors['conflicts'])) {
            if (!isset($currentErrors['conflicts']))
                $currentErrors['conflicts'] = [];
            $currentErrors['conflicts'] = array_merge($currentErrors['conflicts'], $this->chunkErrors['conflicts']);
        }
        if (isset($this->chunkErrors['summary']['duplicates'])) {
            if (!isset($currentErrors['summary']))
                $currentErrors['summary'] = [];
            $oldVal = $currentErrors['summary']['duplicates'] ?? 0;
            $currentErrors['summary']['duplicates'] = $oldVal + $this->chunkErrors['summary']['duplicates'];
        }
        if (isset($this->chunkErrors['details'])) {
            if (!isset($currentErrors['details']))
                $currentErrors['details'] = [];
            if (count($currentErrors['details']) < 1000) {
                $currentErrors['details'] = array_merge($currentErrors['details'], $this->chunkErrors['details']);
            }
        }
        $import->update(['errors' => $currentErrors]);
    }

    private function parseEnum($enumClass, $value)
    {
        if (!$value)
            return null;
        $upper = strtoupper(trim((string) $value));
        try {
            foreach ($enumClass::cases() as $case) {
                if (strtoupper($case->value) === $upper || strtoupper($case->label()) === $upper)
                    return $case;
                if (strtoupper(str_replace('_', ' ', $case->value)) === $upper)
                    return $case;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function loadRoutesCache()
    {
        if (!$this->routesLoaded) {
            $routes = SalesRoute::all();
            foreach ($routes as $r) {
                $this->routeCache[$r->type . '|' . $r->code] = $r->id;
            }
            $this->routesLoaded = true;
        }
    }

    private function ensureRoute($code, $type)
    {
        if (!$code)
            return;
        $key = $type . '|' . $code;
        if (isset($this->routeCache[$key]))
            return;

        $r = SalesRoute::firstOrCreate(['code' => $code, 'type' => $type], ['active' => true]);
        $this->routeCache[$key] = $r->id;
    }

    private function ensureMunicipality(?string $check): ?string
    {
        if (!$check)
            return null;

        $normalized = strtoupper(trim($check));
        // Cache could be added here similar to routes if performance is an issue

        $muni = Municipality::whereRaw('UPPER(name) = ?', [$normalized])->first();

        if (!$muni) {
            $muni = Municipality::create([
                'name' => mb_convert_case($normalized, MB_CASE_TITLE, "UTF-8"), // "Villavicencio" pretty format
                'slug' => Str::slug($normalized),
            ]);
        }

        return $muni->name;
    }

    private function ensureCategory(?string $check): ?string
    {
        if (!$check)
            return null;

        $normalized = trim($check); // No strtoupper for categories, keep case sensitive or title case? Let's assume title case
        $normalized = mb_convert_case($normalized, MB_CASE_TITLE, "UTF-8");

        $cat = StoreCategory::where('name', $normalized)->orWhereRaw('UPPER(name) = ?', [strtoupper($normalized)])->first();

        if (!$cat) {
            $cat = StoreCategory::create([
                'name' => $normalized,
                'slug' => Str::slug($normalized),
                'color' => 'gray' // Default color for new categories
            ]);
        }

        return $cat->name;
    }
}
