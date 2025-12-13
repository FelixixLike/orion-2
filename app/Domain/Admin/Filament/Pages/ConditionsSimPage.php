<?php

namespace App\Domain\Admin\Filament\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class ConditionsSimPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Condiciones SIM';

    protected static ?string $title = 'Módulo de Condiciones SIM';

    protected string $view = 'filament.admin.conditions-simcard.conditions-module';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'conditions-sim';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::guard('admin')->user()?->can('sales_conditions.view') ?? false;
    }

    public function getListUrl(): string
    {
        return ConditionsSimListPage::getUrl(panel: 'admin');
    }

    public function getCreateUrl(): string
    {
        return ConditionsSimCreatePage::getUrl(panel: 'admin');
    }
}
