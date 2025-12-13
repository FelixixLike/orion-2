<?php declare(strict_types=1);

namespace App\Domain\User\Enums;

enum UserRole: string
{
    case SUPERADMIN = 'super_admin';
    case ADMINISTRATOR = 'administrator';
    case TAT_DIRECTION = 'tat_direction';
    case RETAILER = 'retailer';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => "Super Admin",
            self::ADMINISTRATOR => 'Administrador',
            self::TAT_DIRECTION => 'Dirección TAT',
            self::RETAILER => 'Tendero',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'danger',
            self::ADMINISTRATOR => 'warning',
            self::TAT_DIRECTION => 'primary',
            self::RETAILER => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->toArray();
    }

    /**
     * Opciones para el selector de roles (excluyendo SUPERADMIN)
     * El rol super_admin solo se asigna por seeder y no puede ser seleccionado
     * 
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::SUPERADMIN)
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->toArray();
    }

    /** @return array<string, string> */
    public static function badgeColors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->badgeColor() => $role->value])
            ->toArray();
    }
}
