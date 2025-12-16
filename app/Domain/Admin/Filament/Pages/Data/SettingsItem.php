<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages\Data;

readonly class SettingsItem
{
    public function __construct(
        public string $label,
        public string $url,
        public string $description,
    ) {
    }

    public static function mailConfiguration(): self
    {
        // Generar URL directamente usando route sin parámetros
        $url = route('filament.admin.resources.mail-settings.edit');
        
        return new self(
            label: 'Configuración de correo',
            url: $url,
            description: 'Configurar servidor SMTP para envío de correos',
        );
    }
}

