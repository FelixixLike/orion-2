<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionProductResource;
use App\Domain\Store\Models\RedemptionProduct;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RedemptionProductsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Productos redimibles';

    protected static \UnitEnum|string|null $navigationGroup = 'Redenciones';

    protected string $view = 'filament.admin.redeem-store.redeem-store-module';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'redemption-products-module';
    }

    public static function getNavigationSort(): ?int
    {
        return 120;
    }

    public function getBreadcrumbs(): array
    {
        // Oculta el trail "Productos redimibles > Listado" para que solo se muestre el contenido de la tabla.
        return [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();

        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo producto')
                ->url(RedemptionProductResource::getUrl('create'))
                ->visible(fn() => RedemptionProductResource::canCreate()),
        ];
    }

    public function table(Table $table): Table
    {
        // Reutiliza la configuración de tabla del Resource y fija la consulta base requerida por HasTable.
        return RedemptionProductResource::table($table)
            ->query(RedemptionProduct::query());
    }
}
