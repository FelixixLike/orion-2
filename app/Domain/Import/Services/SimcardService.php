<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Services;

use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimcardService
{
    /**
     * Busca o crea una simcard por ICCID limpio
     */
    public static function findOrCreateByIccid(string $iccid, ?string $phoneNumber = null): Simcard
    {
        // 1. Intentar buscar primero
        $simcard = Simcard::where('iccid', $iccid)->first();

        if (!$simcard) {
            // 2. Insertar con ON CONFLICT DO NOTHING para evitar excepción de transacciones
            try {
                // Envolvemos en transacción (Savepoint) por si la sentencia de bajo nivel falla (ej: tipo de dato),
                // para que Postgres no aborte la transacción padre.
                DB::transaction(function () use ($iccid, $phoneNumber) {
                    DB::statement("
                        INSERT INTO simcards (iccid, phone_number, created_at, updated_at)
                        VALUES (?, ?, NOW(), NOW())
                        ON CONFLICT (iccid) DO NOTHING
                    ", [$iccid, $phoneNumber]);
                });
            } catch (\Throwable $e) {
                // Si falla el insert por algo que no sea unique (raro), logueamos pero intentamos buscar
                Log::warning('SimcardService: Raw insert failed', ['error' => $e->getMessage()]);
            }

            // 3. Buscar de nuevo (ahora debe existir)
            $simcard = Simcard::where('iccid', $iccid)->first();
        }

        if ($simcard) {
            // Actualizar phone_number si no existe pero se proporciona
            if ($simcard->phone_number === null && $phoneNumber !== null) {
                $simcard->update(['phone_number' => $phoneNumber]);
            }
            return $simcard;
        }

        throw new \Exception("No se pudo encontrar ni crear la Simcard con ICCID: $iccid");
    }

    /**
     * Busca o crea una simcard por número de teléfono
     */
    public static function findOrCreateByPhoneNumber(string $phoneNumber, ?string $iccid = null): Simcard
    {
        // 1. Intentar buscar primero
        $simcard = Simcard::where('phone_number', $phoneNumber)->first();

        if (!$simcard) {
            // 2. Insertar con ON CONFLICT DO NOTHING
            try {
                DB::transaction(function () use ($phoneNumber, $iccid) {
                    DB::statement("
                        INSERT INTO simcards (phone_number, iccid, created_at, updated_at)
                        VALUES (?, ?, NOW(), NOW())
                        ON CONFLICT (phone_number) DO NOTHING
                    ", [$phoneNumber, $iccid]);
                });
            } catch (\Throwable $e) {
                Log::warning('SimcardService: Raw insert failed (phone)', ['error' => $e->getMessage()]);
            }

            // 3. Buscar de nuevo
            $simcard = Simcard::where('phone_number', $phoneNumber)->first();
        }

        if ($simcard) {
            // Actualizar iccid si no existe pero se proporciona
            if ($simcard->iccid === null && $iccid !== null) {
                $simcard->update(['iccid' => $iccid]);
            }
            return $simcard;
        }

        throw new \Exception("No se pudo encontrar ni crear la Simcard con Teléfono: $phoneNumber");
    }

    /**
     * Busca una simcard por ICCID
     */
    public static function findByIccid(string $iccid): ?Simcard
    {
        return Simcard::where('iccid', $iccid)->first();
    }

    /**
     * Busca una simcard por número de teléfono
     */
    public static function findByPhoneNumber(string $phoneNumber): ?Simcard
    {
        return Simcard::where('phone_number', $phoneNumber)->first();
    }
}

