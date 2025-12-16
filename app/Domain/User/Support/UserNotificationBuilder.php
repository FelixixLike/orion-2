<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\User\Support;

use App\Domain\User\DTOs\AdminWelcomeResult;
use App\Domain\User\DTOs\UserActivationResult;
use Filament\Notifications\Notification;

class UserNotificationBuilder
{
    public static function fromActivationResult(UserActivationResult $result): ?Notification
    {
        if ($result->reason === 'user_not_inactive') {
            return self::buildUserNotInactiveNotification();
        }

        if ($result->isSuccess()) {
            return self::buildActivationSuccessNotification($result);
        }

        return self::buildActivationFailureNotification($result);
    }

    public static function fromAdminWelcomeResult(AdminWelcomeResult $result): ?Notification
    {
        if (!$result->email) {
            return null;
        }

        if ($result->isSuccess()) {
            return self::buildAdminWelcomeSuccessNotification($result);
        }

        return self::buildAdminWelcomeFailureNotification($result);
    }

    private static function buildUserNotInactiveNotification(): Notification
    {
        return Notification::make()
            ->info()
            ->title('Token de activación no generado')
            ->body("El usuario debe estar en estado 'Inactivo' para generar un token de activación.")
            ->persistent();
    }

    private static function buildActivationSuccessNotification(UserActivationResult $result): Notification
    {
        $message = "Se envió un link de activación al email {$result->email}.\n\n";
        $message .= "📋 Información de acceso (por si necesitas compartirla manualmente):\n\n";
        $message .= "🔗 Link de activación:\n{$result->activationUrl}\n\n";
        $message .= "🔑 Token (alternativo):\n{$result->token}\n\n";
        $message .= "⏰ Válido por 48 horas.";

        return Notification::make()
            ->success()
            ->title('✅ Usuario creado exitosamente')
            ->body($message)
            ->persistent();
    }

    private static function buildActivationFailureNotification(UserActivationResult $result): Notification
    {
        $message = "Usuario creado pero el email no se pudo enviar.\n\n";
        $message .= "⚠️ Comparte esta información con el usuario por un canal seguro:\n\n";
        $message .= "🔗 Link de activación:\n{$result->activationUrl}\n\n";
        $message .= "🔑 Token (alternativo):\n{$result->token}\n\n";
        $message .= "⏰ Válido por 48 horas.\n\n";
        $message .= "❌ Error del email: {$result->errorMessage}";

        return Notification::make()
            ->warning()
            ->title('⚠️ Comparte esta información manualmente')
            ->body($message)
            ->persistent();
    }

    private static function buildAdminWelcomeSuccessNotification(AdminWelcomeResult $result): Notification
    {
        $message = "Se envió un email informativo a {$result->email}.\n\n";
        $message .= "📋 Información de acceso (por si necesitas compartirla manualmente):\n\n";
        $message .= "Email: {$result->email}\n";
        $message .= "Contraseña: {$result->password}\n";
        $message .= "URL de acceso: {$result->loginUrl}";

        return Notification::make()
            ->success()
            ->title('✅ Usuario creado exitosamente')
            ->body($message)
            ->persistent();
    }

    private static function buildAdminWelcomeFailureNotification(AdminWelcomeResult $result): Notification
    {
        $message = "Usuario creado pero el email no se pudo enviar.\n\n";
        $message .= "⚠️ Comparte esta información con el usuario por un canal seguro:\n\n";
        $message .= "Email: {$result->email}\n";
        $message .= "Contraseña: {$result->password}\n";
        $message .= "URL de acceso: {$result->loginUrl}\n\n";
        $message .= "❌ Error del email: {$result->errorMessage}";

        return Notification::make()
            ->warning()
            ->title('⚠️ Comparte esta información manualmente')
            ->body($message)
            ->persistent();
    }
}
