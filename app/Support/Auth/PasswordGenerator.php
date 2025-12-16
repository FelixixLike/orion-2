<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Support\Auth;

/**
 * Genera contraseñas seguras con requisitos específicos.
 * 
 * Todas las contraseñas generadas cumplen con:
 * - Mínimo 8 caracteres
 * - Al menos una mayúscula
 * - Al menos una minúscula
 * - Al menos un número
 */
class PasswordGenerator
{
    private const MIN_LENGTH = 8;
    private const MAX_LENGTH = 16;

    /**
     * Genera una contraseña segura aleatoria.
     *
     * @param int $length Longitud de la contraseña (entre 8 y 16)
     * @return string Contraseña generada
     */
    public static function generate(int $length = 12): string
    {
        // Validar longitud
        $length = max(self::MIN_LENGTH, min($length, self::MAX_LENGTH));

        // Definir conjuntos de caracteres
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Sin I, O para evitar confusión
        $lowercase = 'abcdefghjkmnpqrstuvwxyz'; // Sin i, l, o para evitar confusión
        $numbers = '23456789'; // Sin 0, 1 para evitar confusión
        $special = '!@#$%&*';

        // Asegurar al menos uno de cada tipo requerido
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        // Rellenar el resto con caracteres aleatorios
        $allChars = $uppercase . $lowercase . $numbers . $special;
        $remainingLength = $length - 3;

        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Mezclar los caracteres para que no sean predecibles
        return str_shuffle($password);
    }

    /**
     * Valida si una contraseña cumple con los requisitos.
     *
     * @param string $password Contraseña a validar
     * @return bool
     */
    public static function isValid(string $password): bool
    {
        return strlen($password) >= self::MIN_LENGTH
            && preg_match('/[A-Z]/', $password) // Al menos una mayúscula
            && preg_match('/[a-z]/', $password) // Al menos una minúscula
            && preg_match('/\d/', $password);   // Al menos un número
    }

    /**
     * Obtiene los requisitos de contraseña en formato legible.
     *
     * @return string
     */
    public static function getRequirements(): string
    {
        return sprintf(
            'Requisitos: • Mínimo %d caracteres • Al menos una mayúscula • Al menos una minúscula • Al menos un número',
            self::MIN_LENGTH
        );
    }

    /**
     * Genera múltiples contraseñas seguras.
     *
     * @param int $count Cantidad de contraseñas a generar
     * @param int $length Longitud de cada contraseña
     * @return array
     */
    public static function generateMultiple(int $count, int $length = 12): array
    {
        $passwords = [];
        
        for ($i = 0; $i < $count; $i++) {
            $passwords[] = self::generate($length);
        }

        return $passwords;
    }
}

