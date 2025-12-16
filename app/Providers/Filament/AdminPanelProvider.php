<?php

namespace App\Providers\Filament;

use App\Domain\Admin\Filament\Resources\RetailerResource;
use App\Domain\Admin\Filament\Resources\RedemptionProductResource;
use App\Domain\Admin\Filament\Resources\RedemptionResource;
use App\Domain\Admin\Filament\Pages\StoreStatementPage;
use App\Http\Middleware\AdminPanelAuthenticate;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin/dashboard')
            ->authGuard('admin')
            ->brandName('Panel de Administracion')
            ->font(
                'Montserrat',
                'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
                LocalFontProvider::class
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                // Paleta vino para claro y oscuro
                'primary' => Color::hex('#9d0b1d'),
                'secondary' => Color::hex('#b53a32'),
            ])
            ->darkMode(true)
            ->spa()
            ->renderHook(
                'panels::styles.after',
                fn() => '<style>
                    input[type="number"]::-webkit-inner-spin-button,
                    input[type="number"]::-webkit-outer-spin-button {
                        -webkit-appearance: none;
                        margin: 0;
                    }
                    input[type="number"] {
                        -moz-appearance: textfield;
                        appearance: textfield;
                    }
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                    .spinner-animate {
                        animation: spin 1s linear infinite;
                        display: inline-block;
                        vertical-align: middle;
                    }
                </style>'
            )
            // Widget Personalizado Eliminado por Solicitud del Usuario (Prefiere notificaciones nativas)
            // ->renderHook(
            //     'panels::body.end',
            //     fn() => \Illuminate\Support\Facades\Blade::render("@livewire('background-process-widget')")
            // )
            ->discoverResources(in: app_path('Domain/Admin/Filament/Resources'), for: 'App\Domain\Admin\Filament\Resources')
            ->resources([
                RedemptionResource::class,
                RedemptionProductResource::class,
                RetailerResource::class,
            ])
            ->discoverPages(in: app_path('Domain/Admin/Filament/Pages'), for: 'App\Domain\Admin\Filament\Pages')
            ->pages([
                \App\Domain\Admin\Filament\Pages\Auth\EditProfile::class,
                \App\Domain\Admin\Filament\Pages\AdminDashboard::class,
                \App\Domain\Admin\Filament\Pages\CrossingsPage::class,
                \App\Domain\Admin\Filament\Pages\ConditionsSimPage::class,
                \App\Domain\Admin\Filament\Pages\RetailersPage::class,
                \App\Domain\Admin\Filament\Pages\ImportModulePage::class,
                \App\Domain\Admin\Filament\Pages\StoresModulePage::class,
                \App\Domain\Admin\Filament\Pages\UsersModulePage::class,
                StoreStatementPage::class,
            ])
            ->discoverWidgets(in: app_path('Domain/Admin/Filament/Widgets'), for: 'App\Domain\Admin\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                StartSession::class,
                AuthenticateSession::class, // Preceded by StartSession
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Contraseña')
                    ->url(fn(): string => \App\Domain\Admin\Filament\Pages\Auth\EditProfile::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->authMiddleware([
                AdminPanelAuthenticate::class,
                \App\Http\Middleware\EnsurePasswordChanged::class . ':admin',
            ]);
    }
}
