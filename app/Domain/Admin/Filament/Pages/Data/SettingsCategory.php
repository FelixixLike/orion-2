<?php

namespace App\Domain\Admin\Filament\Pages\Data;

readonly class SettingsCategory
{
    public function __construct(
        public string $name,
        public string $icon,
        public string $description,
        /** @var array<SettingsItem> */
        public array $items,
    ) {
    }

    /**
     * @return array<self>
     */
    public static function all(): array
    {
        return [
            self::mail(),
            // Aquí se pueden añadir más categorías en el futuro
        ];
    }

    private static function mail(): self
    {
        return new self(
            name: 'Correo',
            icon: 'heroicon-o-envelope',
            description: 'Configuración de envío de correos electrónicos',
            items: [
                SettingsItem::mailConfiguration(),
            ],
        );
    }
}

