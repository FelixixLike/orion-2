<?php

namespace App\Domain\Import\Imports;

use App\Domain\Route\Models\Route as SalesRoute;
use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Enums\StoreCategory;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use App\Domain\Import\Models\Import;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

class StoreImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets
{
    private int $createdStores = 0;
    private int $updatedStores = 0;
    private int $createdUsers = 0;
    private int $processedRows = 0;
    private array $errors = [];
    private ?int $importId = null;
    private bool $updateConflictingUsers = false;
    private ?int $createdBy = null;

    public function __construct(?int $importId = null, bool $updateConflictingUsers = false)
    {
        $this->importId = $importId;
        $this->updateConflictingUsers = $updateConflictingUsers;

        if ($importId) {
            $import = Import::find($importId);
            $this->createdBy = $import?->created_by;
        }
    }

    public function sheets(): array
    {
        return [
            0 => $this, // Process first sheet
        ];
    }

    public function onRow(Row $row)
    {
        $rowArray = $row->toArray();

        try {
            // Mapping (permitir encabezados truncados en cédula).
            $doc = $rowArray['nro_documento_cliente']
                ?? $rowArray['nro_docun']
                ?? $rowArray['documento']
                ?? null;
            $clientName = $rowArray['nombre_cliente'] ?? null;
            $clientLastName = $rowArray['apellido_cliente'] ?? null;
            $clientPhone = $rowArray['telefono_cliente'] ?? null;
            $clientEmail = $rowArray['correo_electronico_cliente'] ?? null;

            $idpos = $rowArray['id_pdv'] ?? null;
            $routeCode = $rowArray['ruta'] ?? null;
            $circuitCode = $rowArray['circuito'] ?? null;
            $storeName = $rowArray['nombre_punto'] ?? null;
            $category = $rowArray['categoria'] ?? null;
            $storePhone = $rowArray['celular'] ?? null;
            $municipality = $rowArray['municipio'] ?? null;
            $hood = $rowArray['barrio'] ?? null;
            $address = $rowArray['direccion'] ?? null;
            $storeEmail = $rowArray['correo_electronico'] ?? null;

            if (!$idpos || !$storeName) {
                return;
            }
            $this->processedRows++;

            // Tendero
            $user = null;
            $hasConflict = false;
            if ($doc) {
                $existing = User::where('id_number', $doc)->first();

                $firstName = trim((string) ($clientName ?? ''));
                $lastName = trim((string) ($clientLastName ?? ''));

                if (!$lastName && $firstName) {
                    $parts = explode(' ', $firstName);
                    $lastName = array_pop($parts);
                    $firstName = trim(implode(' ', $parts)) ?: $lastName;
                }

                if ($existing) {
                    $nameMatches = true;
                    $emailMatches = true;

                    if ($firstName && strcasecmp($existing->first_name ?? '', $firstName) !== 0) {
                        $nameMatches = false;
                    }
                    if ($lastName && strcasecmp($existing->last_name ?? '', $lastName) !== 0) {
                        $nameMatches = false;
                    }
                    if ($clientEmail && strcasecmp($existing->email ?? '', $clientEmail) !== 0) {
                        $emailMatches = false;
                    }

                    if ($nameMatches && $emailMatches) {
                        $user = $existing;
                    } else {
                        if ($this->updateConflictingUsers) {
                            $existing->update([
                                'first_name' => $firstName ?: $existing->first_name,
                                'last_name' => $lastName ?: $existing->last_name,
                                'email' => $clientEmail ?: $existing->email,
                                'phone' => $clientPhone ?: $existing->phone,
                            ]);
                            $user = $existing->fresh();
                        } else {
                            $hasConflict = true;
                            $this->errors[] = [
                                'type' => 'user_conflict',
                                'row' => $row->getIndex(),
                                'idpos' => $idpos ?? null,
                                'id_number' => $doc,
                                'existing' => [
                                    'first_name' => $existing->first_name,
                                    'last_name' => $existing->last_name,
                                    'email' => $existing->email,
                                    'phone' => $existing->phone,
                                ],
                                'incoming' => [
                                    'first_name' => $firstName ?: null,
                                    'last_name' => $lastName ?: null,
                                    'email' => $clientEmail ?: null,
                                    'phone' => $clientPhone ?: null,
                                ],
                                'message' => "Conflicto: cédula {$doc} ya existe con otros datos (nombre/correo). Tienda inactiva y sin tendero.",
                                'action' => 'pending',
                            ];
                        }
                    }
                } else {
                    if (!$firstName) {
                        $hasConflict = true;
                        $this->errors[] = [
                            'type' => 'user_missing_name',
                            'row' => $row->getIndex(),
                            'idpos' => $idpos ?? null,
                            'id_number' => $doc,
                            'incoming' => [
                                'first_name' => $firstName ?: null,
                                'last_name' => $lastName ?: null,
                                'email' => $clientEmail ?: null,
                                'phone' => $clientPhone ?: null,
                            ],
                            'message' => "No se creó tendero para la cédula {$doc}: falta nombre.",
                            'action' => 'pending',
                        ];
                    } elseif ($firstName || $lastName || $clientPhone || $clientEmail) {
                        $username = (string) $doc;
                        if (User::where('username', $username)->exists()) {
                            $username = $doc . '_' . Str::random(4);
                        }

                        $email = $clientEmail ?: ($doc . '@placeholder.com');

                        $user = User::create([
                            'first_name' => $firstName ?: 'N/A',
                            'last_name' => $lastName,
                            'id_number' => $doc,
                            'phone' => $clientPhone,
                            'email' => $email,
                            'password_hash' => Hash::make(Str::random(12)),
                            'status' => 'inactive',
                            'username' => $username,
                        ]);

                        $user->assignRole('retailer');
                        $this->createdUsers++;
                    }
                }
            }

            $store = Store::where('idpos', $idpos)->first();

            if ($routeCode) {
                SalesRoute::firstOrCreate(['code' => $routeCode, 'type' => 'route'], ['active' => true]);
            }
            if ($circuitCode) {
                SalesRoute::firstOrCreate(['code' => $circuitCode, 'type' => 'circuit'], ['active' => true]);
            }

            $catEnum = null;
            if ($category) {
                $catUpper = strtoupper(trim((string) $category));
                foreach (StoreCategory::cases() as $c) {
                    if (strtoupper($c->value) === $catUpper || strtoupper($c->label()) === $catUpper) {
                        $catEnum = $c;
                        break;
                    }
                }
            }

            $muniEnum = null;
            if ($municipality) {
                $muniUpper = strtoupper(trim((string) $municipality));
                foreach (Municipality::cases() as $m) {
                    if (strtoupper($m->value) === $muniUpper || strtoupper($m->label()) === $muniUpper) {
                        $muniEnum = $m;
                        break;
                    }
                    $normalizedValue = strtoupper(str_replace('_', ' ', $m->value));
                    if ($normalizedValue === $muniUpper) {
                        $muniEnum = $m;
                        break;
                    }
                }
            }

            $data = [
                'idpos' => $idpos,
                'name' => $storeName,
                'route_code' => $routeCode,
                'circuit_code' => $circuitCode,
                'category' => $catEnum,
                'phone' => $storePhone,
                'municipality' => $muniEnum,
                'neighborhood' => $hood,
                'address' => $address,
                'email' => $storeEmail,
                'user_id' => $user?->id,
                'status' => ($user && !$hasConflict) ? StoreStatus::ACTIVE : StoreStatus::INACTIVE,
            ];

            $storeConflict = false;
            if ($store) {
                if (!$user) {
                    $data['user_id'] = $store->user_id;
                    $data['status'] = $store->status;
                }

                if ($storeName && strcasecmp((string) $store->name, (string) $storeName) !== 0) {
                    $storeConflict = true;
                }
                if ($catEnum && $store->category && $store->category !== $catEnum) {
                    $storeConflict = true;
                }
                if ($muniEnum && $store->municipality && $store->municipality !== $muniEnum) {
                    $storeConflict = true;
                }

                if ($storeConflict) {
                    $this->errors[] = [
                        'type' => 'store_conflict',
                        'row' => $row->getIndex(),
                        'idpos' => $idpos,
                        'store_id' => $store->id,
                        'existing' => [
                            'name' => $store->name,
                            'category' => $store->category ? $store->category->value : null,
                            'municipality' => $store->municipality ? $store->municipality->value : null,
                        ],
                        'incoming' => [
                            'name' => $storeName,
                            'category' => $catEnum?->value,
                            'municipality' => $muniEnum?->value,
                        ],
                        'message' => "La tienda ID_PDV {$idpos} ya existe con otros datos (nombre/categoría/municipio). No se actualizó ningún dato de la tienda.",
                        'action' => 'pending',
                    ];
                } else {
                    if ($this->createdBy) {
                        $data['updated_by'] = $this->createdBy;
                    }
                    $store->update($data);
                    $this->updatedStores++;
                    if ($store && $user && !$hasConflict) {
                        $store->users()->sync([$user->id]);
                    }
                }
            } else {
                if ($this->createdBy) {
                    $data['created_by'] = $this->createdBy;
                    $data['updated_by'] = $this->createdBy;
                }
                $store = Store::create($data);
                $this->createdStores++;
                if ($store && $user && !$hasConflict) {
                    $store->users()->sync([$user->id]);
                }
            }
        } catch (\Throwable $e) {
            $this->errors[] = [
                'type' => 'exception',
                'row' => $row->getIndex(),
                'idpos' => $rowArray['id_pdv'] ?? null,
                'message' => $e->getMessage(),
                'action' => 'pending',
            ];
        }
    }

    public function getStats(): array
    {
        return [
            'created_stores' => $this->createdStores,
            'updated_stores' => $this->updatedStores,
            'created_users' => $this->createdUsers,
            'total_processed' => $this->processedRows,
            'errors' => $this->errors,
        ];
    }

    public function getErrors(): array
    {
        return [
            'summary' => [
                'total_processed' => $this->processedRows,
                'created_stores' => $this->createdStores,
                'updated_stores' => $this->updatedStores,
                'created_users' => $this->createdUsers,
                'conflicts' => count($this->errors),
            ],
            'conflicts' => $this->errors,
        ];
    }
}
