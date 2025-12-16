<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Store\Enums;

enum StoreCategory: string
{
    case ORO = 'oro';
    case PLATA = 'plata';
    case PLATINO = 'platino';

    public function label(): string
    {
        return match ($this) {
            self::ORO => 'Oro',
            self::PLATA => 'Plata',
            self::PLATINO => 'Platino',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->label()])
            ->toArray();
    }

    /** @return array<string, string> */
    public static function badgeColors(): array
    {
        return [
            'warning' => self::ORO->value,
            'secondary' => self::PLATA->value,
            'primary' => self::PLATINO->value,
        ];
    }
}
