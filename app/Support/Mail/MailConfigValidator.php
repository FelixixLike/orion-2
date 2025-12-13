<?php

namespace App\Support\Mail;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class MailConfigValidator
{
    /**
     * Verifica si la configuración de correo está completa.
     *
     * @return bool
     */
    public static function isConfigured(): bool
    {
        $mailer = config('mail.default');
        
        // Si está en modo log o array, no está configurado para enviar correos reales
        if (in_array($mailer, ['log', 'array'])) {
            return false;
        }
        
        // Para SMTP, verificar que tenga las credenciales necesarias
        if ($mailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');
            
            if (empty($host) || empty($port) || empty($username) || empty($password)) {
                return false;
            }
        }
        
        // Para servicios de terceros, verificar que tenga el token/key necesario
        if ($mailer === 'postmark') {
            $token = config('services.postmark.token');
            if (empty($token)) {
                return false;
            }
        }
        
        if ($mailer === 'resend') {
            $key = config('services.resend.key');
            if (empty($key)) {
                return false;
            }
        }
        
        if (in_array($mailer, ['ses', 'ses-v2'])) {
            $key = config('services.ses.key');
            $secret = config('services.ses.secret');
            if (empty($key) || empty($secret)) {
                return false;
            }
        }
        
        // Verificar que tenga una dirección de origen configurada
        $fromAddress = config('mail.from.address');
        if (empty($fromAddress) || $fromAddress === 'hello@example.com') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtiene el mensaje de error cuando la configuración no está completa.
     *
     * @return string
     */
    public static function getErrorMessage(): string
    {
        return 'No tienes configuración para enviar correos. Por favor, configura las variables de entorno de correo (MAIL_*) antes de intentar enviar emails.';
    }
    
    /**
     * Valida la configuración de correo y lanza excepción si no está configurado.
     * Incluye la notificación visual para el usuario.
     *
     * @throws ValidationException
     */
    public static function validateOrFail(): void
    {
        if (!self::isConfigured()) {
            Notification::make()
                ->danger()
                ->title('Error: Configuración de correo requerida')
                ->body(self::getErrorMessage())
                ->persistent()
                ->send();
            
            throw ValidationException::withMessages([
                'form' => [self::getErrorMessage()],
            ]);
        }
    }
}
