<?php declare(strict_types=1);

namespace App\Domain\Store\Enums;

enum StoreStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::INACTIVE => 'Inactiva',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->toArray();
    }

    /** @return array<string, string> */
    public static function badgeColors(): array
    {
        return [
            'success' => self::ACTIVE->value,
            'danger' => self::INACTIVE->value,
        ];
    }
}
