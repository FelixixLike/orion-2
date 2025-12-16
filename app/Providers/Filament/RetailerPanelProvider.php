<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Providers\Filament;

use App\Domain\Retailer\Filament\Pages\BalancePage;
use App\Domain\Retailer\Filament\Pages\BalanceMovementDetailPage;
use App\Domain\Retailer\Filament\Pages\PortalDashboard;
use App\Domain\Retailer\Filament\Pages\StoreCatalogPage;
use App\Domain\Retailer\Filament\Pages\StoresPage;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class RetailerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('retailer')
            ->path('portal')
            ->authGuard('retailer')
            ->login(\App\Domain\Retailer\Filament\Pages\Auth\Login::class)
            ->brandName('Panel de Tenderos')
            ->font(
                'Montserrat',
                'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
                LocalFontProvider::class
            )
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex('#c8102e'),
            ])
            ->darkMode(true)
            ->viteTheme('resources/css/filament/retailer/theme.css')
            ->spa()
            ->discoverResources(in: app_path('Domain/Retailer/Filament/Resources'), for: 'App\Domain\Retailer\Filament\Resources')
            ->discoverPages(in: app_path('Domain/Retailer/Filament/Pages'), for: 'App\Domain\Retailer\Filament\Pages')
            ->pages([
                \App\Domain\Retailer\Filament\Pages\Auth\EditProfile::class,
                PortalDashboard::class,
                BalancePage::class,
                BalanceMovementDetailPage::class,
                StoreCatalogPage::class,
                StoresPage::class,
            ])
            ->discoverWidgets(in: app_path('Domain/Retailer/Filament/Widgets'), for: 'App\Domain\Retailer\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DispatchServingFilamentEvent::class,
            ])
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Contraseña')
                    ->url(fn(): string => \App\Domain\Retailer\Filament\Pages\Auth\EditProfile::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsurePasswordChanged::class . ':retailer',
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn() => view('filament.retailer.partials.theme-switcher-fix'),
            );
    }
}
