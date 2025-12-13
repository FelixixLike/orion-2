<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Resources\ImportResource;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class ImportModulePage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Importación';

    protected static ?string $title = 'Módulo de Importación';

    protected string $view = 'filament.admin.import.import-module';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'imports-module';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public function getImportCreateUrl(): string
    {
        return ImportResource::getUrl('create');
    }

    public function getImportListUrl(): string
    {
        return ImportResource::getUrl('index');
    }
}
