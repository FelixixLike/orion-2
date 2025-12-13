<?php

namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Import\Models\Import;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\RedemptionProduct;
use App\Domain\Store\Models\Redemption;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected string $view = 'filament.admin.pages.admin-dashboard';

    public int $activeStores = 0;
    public int $monthLiquidations = 0;
    public int $pendingRedemptions = 0;
    public int $tenderersCount = 0;
    public int $importsInProgress = 0;
    public int $activeProductsCount = 0;
    public string $roleSummary = '';

    public $recentLiquidations = [];
    public $recentRedemptions = [];
    public $recentImports = [];
    public $recentStores = [];

    public static function getNavigationSort(): ?int
    {
        return -100;
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return '/';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasRole(['super_admin', 'administrator', 'tat_direction'], 'admin') ?? false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        // ... (código existente)
        $user = Auth::guard('admin')->user();

        $this->roleSummary = $user?->roles
            ? $user->roles->pluck('name')->implode(', ')
            : '';

        // Tiendas activas
        $this->activeStores = Store::query()
            ->where('status', 'active')
            ->count();

        $startOfMonth = Carbon::now()->startOfMonth();

        // Cantidad de liquidaciones cerradas este mes
        $this->monthLiquidations = Liquidation::query()
            ->whereDate('created_at', '>=', $startOfMonth)
            ->count();

        // Redenciones pendientes por aprobar/entregar
        $this->pendingRedemptions = Redemption::query()
            ->where('status', 'pending')
            ->count();

        // Tenderos activos (usuarios con rol retailer, guard retailer)
        $this->tenderersCount = User::query()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'retailer')
                    ->where('guard_name', 'retailer');
            })
            ->where('status', 'active')
            ->count();

        // Importaciones en curso (si el rol tiene acceso a importaciones)
        if ($user && $user->hasPermissionTo('imports.view', 'admin')) {
            $this->importsInProgress = Import::query()
                ->whereIn('status', ['queued', 'processing'])
                ->count();

            $this->recentImports = Import::query()
                ->with('creator:id,first_name,last_name')
                ->latest('created_at')
                ->limit(5)
                ->get();
        }

        // Productos redimibles activos (para admins / TAT que los gestionan)
        if ($user && $user->hasPermissionTo('redemption_products.view', 'admin')) {
            $this->activeProductsCount = RedemptionProduct::query()
                ->where('is_active', true)
                ->count();
        }

        // Tiendas creadas recientemente
        $this->recentStores = Store::query()
            ->latest('created_at')
            ->limit(5)
            ->get();

        $this->recentLiquidations = Liquidation::query()
            ->with('store')
            ->latest('created_at')
            ->limit(5)
            ->get();

        $this->recentRedemptions = Redemption::query()
            ->with(['store', 'redemptionProduct'])
            ->latest('requested_at')
            ->limit(5)
            ->get();
    }


}
