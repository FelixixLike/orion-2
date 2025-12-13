<?php

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

