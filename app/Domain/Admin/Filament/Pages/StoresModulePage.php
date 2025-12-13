<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Resources\StoreResource;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class StoresModulePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Tiendas';

    protected static ?string $title = 'Módulo de Tiendas';

    protected string $view = 'filament.admin.stores.stores-module';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'stores-module';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public function getStoreListUrl(): string
    {
        return StoreResource::getUrl('index');
    }

    public function getStoreCreateUrl(): string
    {
        return StoreResource::getUrl('create');
    }
}
