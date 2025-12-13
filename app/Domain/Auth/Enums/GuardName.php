<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

enum GuardName: string
{
    case ADMIN = 'admin';
    case RETAILER = 'retailer';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::RETAILER => 'Tendero',
        };
    }

    public function loginRoute(): string
    {
        return match($this) {
            self::ADMIN => '/admin/login',
            self::RETAILER => '/portal/login',
        };
    }

    public function loginUrl(): string
    {
        return url($this->loginRoute());
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isRetailer(): bool
    {
        return $this === self::RETAILER;
    }
}
