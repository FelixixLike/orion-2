<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Resources\UserResource;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class UsersModulePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $title = 'Módulo de Usuarios';

    protected string $view = 'filament.admin.users.user-module';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'users-module';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        // Solo el super_admin puede administrar usuarios.
        return $user?->hasRole('super_admin', 'admin') ?? false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function getUserListUrl(): string
    {
        return UserResource::getUrl('index');
    }

    public function getUserCreateUrl(): string
    {
        return UserResource::getUrl('create');
    }
}
