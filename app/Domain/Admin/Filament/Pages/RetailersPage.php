<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Resources\RetailerResource;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class RetailersPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Tenderos';

    protected static ?string $title = 'Módulo de Tenderos';

    protected string $view = 'filament.admin.retailer.retailer-module';

    public static function getSlug(?Panel $panel = null): string
    {
        // Usamos un slug distinto al del Resource para evitar conflictos con
        // `filament.admin.resources.retailers.*`.
        return 'retailers-module';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public function getRetailerListUrl(): string
    {
        return RetailerResource::getUrl('index');
    }

    public function getRetailerCreateUrl(): string
    {
        return RetailerResource::getUrl('create');
    }

    public function getStoresModuleUrl(): string
    {
        return StoresModulePage::getUrl(panel: 'admin');
    }
}
