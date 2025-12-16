<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            $setting = \App\Domain\Mail\Models\MailSetting::first();
            
            if ($setting) {
                // Actualizar configuración de correo desde la base de datos
                Config::set('mail.default', $setting->mailer ?? 'smtp');
                
                Config::set('mail.mailers.smtp.host', $setting->host ?? config('mail.mailers.smtp.host', '127.0.0.1'));
                Config::set('mail.mailers.smtp.port', $setting->port ?? config('mail.mailers.smtp.port', 2525));
                Config::set('mail.mailers.smtp.username', $setting->from_address ?? config('mail.mailers.smtp.username'));
                Config::set('mail.mailers.smtp.password', $setting->password ?? config('mail.mailers.smtp.password'));
                Config::set('mail.mailers.smtp.encryption', $setting->encryption ?? config('mail.mailers.smtp.encryption'));
                
                Config::set('mail.from.address', $setting->from_address ?? config('mail.from.address', 'hello@example.com'));
                Config::set('mail.from.name', $setting->from_name ?? config('mail.from.name', 'Example'));
            }
        } catch (\Exception $e) {
            // Si hay algún error (tabla no existe, etc.), usar valores del .env
            // No hacer nada, Laravel usará los valores por defecto del config/mail.php
        }
    }
}

