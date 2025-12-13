<?php

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
        try {
            $simcard = Simcard::firstOrCreate(
                ['iccid' => $iccid],
                ['phone_number' => $phoneNumber]
            );

            // Actualizar phone_number si no existe pero se proporciona
            /** @var string|null $currentPhone */
            $currentPhone = $simcard->phone_number;
            if ($currentPhone === null && $phoneNumber !== null) {
                $simcard->update(['phone_number' => $phoneNumber]);
            }

            return $simcard;
        } catch (\Throwable $e) {
            // Manejar violación de constraint único (race condition)
            // Código 23505 = unique_violation en PostgreSQL
            $isUniqueViolation = $e->getCode() == 23505 || 
                                 str_contains($e->getMessage(), '23505') || 
                                 str_contains($e->getMessage(), 'unique constraint') ||
                                 str_contains($e->getMessage(), 'duplicate key value');

            if ($isUniqueViolation) {
                // Si es violación única, hacer SELECT directo
                // Esto evita corromper la transacción principal
                Log::debug('SimcardService: Unique violation caught for ICCID, performing direct SELECT', [
                    'iccid' => $iccid,
                    'phone_number' => $phoneNumber
                ]);
                
                $simcard = Simcard::where('iccid', $iccid)->first();
                
                if ($simcard) {
                    // Actualizar phone_number si no existe pero se proporciona
                    /** @var string|null $currentPhone */
                    $currentPhone = $simcard->phone_number;
                    if ($currentPhone === null && $phoneNumber !== null) {
                        $simcard->update(['phone_number' => $phoneNumber]);
                    }
                    
                    return $simcard;
                }
            }
            
            // Si no es violación única o no se encontró el registro, re-lanzar
            throw $e;
        }
    }

    /**
     * Busca o crea una simcard por número de teléfono
     */
    public static function findOrCreateByPhoneNumber(string $phoneNumber, ?string $iccid = null): Simcard
    {
        try {
            $simcard = Simcard::firstOrCreate(
                ['phone_number' => $phoneNumber],
                ['iccid' => $iccid]
            );

            // Actualizar iccid si no existe pero se proporciona
            /** @var string|null $currentIccid */
            $currentIccid = $simcard->iccid;
            if ($currentIccid === null && $iccid !== null) {
                $simcard->update(['iccid' => $iccid]);
            }

            return $simcard;
        } catch (\Throwable $e) {
            // Manejar violación de constraint único (race condition)
            $isUniqueViolation = $e->getCode() == 23505 || 
                                 str_contains($e->getMessage(), '23505') || 
                                 str_contains($e->getMessage(), 'unique constraint') ||
                                 str_contains($e->getMessage(), 'duplicate key value');

            if ($isUniqueViolation) {
                // Si es violación única, hacer SELECT directo
                Log::debug('SimcardService: Unique violation caught for phone number, performing direct SELECT', [
                    'phone_number' => $phoneNumber,
                    'iccid' => $iccid
                ]);
                
                $simcard = Simcard::where('phone_number', $phoneNumber)->first();
                
                if ($simcard) {
                    // Actualizar iccid si no existe pero se proporciona
                    /** @var string|null $currentIccid */
                    $currentIccid = $simcard->iccid;
                    if ($currentIccid === null && $iccid !== null) {
                        $simcard->update(['iccid' => $iccid]);
                    }
                    
                    return $simcard;
                }
            }
            
            // Si no es violación única o no se encontró el registro, re-lanzar
            throw $e;
        }
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

