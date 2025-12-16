<?php

namespace App\Providers;

use App\Domain\Route\Models\Route;
use App\Domain\Route\Policies\RoutePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar componente Livewire manualmente para evitar problemas de ruta
        \Livewire\Livewire::component('background-process-widget', \App\Domain\Admin\Livewire\BackgroundProcessWidget::class);

        // Registrar notification de login
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\SendLoginNotification::class,
        );

        Gate::before(function ($user, $ability) {
            return $user->hasRole(\App\Domain\User\Enums\UserRole::SUPERADMIN->value) ? true : null;
        });

        Gate::policy(Route::class, RoutePolicy::class);

        $this->synchronizeAppUrlWithRequest();

        // Optimizaciones de rendimiento
        $this->optimizePerformance();
    }

    /**
     * Optimizar rendimiento de la aplicación
     */
    private function optimizePerformance(): void
    {
        // Habilitar query logging en desarrollo
        if (config('app.debug')) {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        }

        // Configurar max connections pool
        if (config('database.default') === 'pgsql') {
            // PostgreSQL maneja mejor el pool con estas configuraciones
            config([
                'database.connections.pgsql.pooling' => true,
                'database.connections.pgsql.pool.min_size' => 5,
                'database.connections.pgsql.pool.max_size' => 20,
            ]);
        }

        // Usar eager loading en modelos para evitar N+1 queries
        $this->configureModelDefaults();
    }

    /**
     * Configurar valores por defecto de modelos
     */
    private function configureModelDefaults(): void
    {
        // Los modelos cargarán relaciones automáticamente si es necesario
        // Esto se configura en cada Resource de Filament
    }

    /**
     * Ajusta dinámicamente la URL base según el host que esté atendiendo la petición.
     * Esto permite trabajar al mismo tiempo en entorno local y a través de túneles/Cloudflare,
     * evitando que Livewire genere solicitudes hacia "localhost" cuando el usuario está en la nube.
     */
    private function synchronizeAppUrlWithRequest(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        if (!$request) {
            return;
        }

        $schemeAndHost = $request->getSchemeAndHttpHost();
        if (empty($schemeAndHost)) {
            return;
        }

        config(['app.url' => $schemeAndHost]);

        URL::forceRootUrl($schemeAndHost);

        if ($request->isSecure()) {
            URL::forceScheme('https');
        }
    }
}
