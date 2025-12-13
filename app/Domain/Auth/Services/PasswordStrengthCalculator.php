<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

class PasswordStrengthCalculator
{
    public const MIN_LENGTH = 8;
    public const RECOMMENDED_LENGTH = 12;

    /**
     * Valida si una contraseña cumple con los requisitos mínimos
     */
    public static function meetsRequirements(?string $password): bool
    {
        if (!$password || strlen($password) < self::MIN_LENGTH) {
            return false;
        }

        return preg_match('/[a-z]/', $password) === 1 // Minúscula
            && preg_match('/[A-Z]/', $password) === 1  // Mayúscula
            && preg_match('/\d/', $password) === 1      // Número
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1; // Especial
    }

    /**
     * Obtiene lista de requisitos no cumplidos
     * 
     * @return array<string>
     */
    public static function getMissingRequirements(?string $password): array
    {
        $missing = [];

        if (!$password) {
            return ['Ingresa una contraseña'];
        }

        if (strlen($password) < self::MIN_LENGTH) {
            $missing[] = 'Mínimo ' . self::MIN_LENGTH . ' caracteres';
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            $missing[] = 'Una letra minúscula';
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            $missing[] = 'Una letra mayúscula';
        }

        if (preg_match('/\d/', $password) !== 1) {
            $missing[] = 'Un número';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
            $missing[] = 'Un carácter especial (!@#$%...)';
        }

        return $missing;
    }

    /**
     * Obtiene las reglas de validación de Laravel
     */
    public static function getLaravelRules(): array
    {
        return [
            'required',
            'string',
            'min:' . self::MIN_LENGTH,
            'regex:/[a-z]/',      // Al menos una minúscula
            'regex:/[A-Z]/',      // Al menos una mayúscula
            'regex:/[0-9]/',      // Al menos un número
            'regex:/[^a-zA-Z0-9]/', // Al menos un carácter especial
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados
     */
    public static function getValidationMessages(): array
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.regex' => 'La contraseña debe contener mayúsculas, minúsculas, números y caracteres especiales.',
        ];
    }
}

