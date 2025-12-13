<?php

use App\Domain\Auth\Controllers\AdminAuthController;
use App\Domain\Auth\Controllers\RetailerAuthController;
use App\Domain\Auth\Controllers\AccountActivationController;
use App\Domain\Auth\Controllers\PasswordChangeController;
use App\Domain\Retailer\Controllers\ActiveStoreController;
use App\Domain\Retailer\Controllers\BalanceMovementExportController;
use App\Domain\Retailer\Controllers\BalanceMovementDetailController;
use App\Domain\Retailer\Filament\Pages\BalancePage as RetailerBalancePage;
use App\Domain\Retailer\Filament\Pages\PortalDashboard;
use App\Domain\Retailer\Filament\Pages\StoreCatalogPage;
use App\Domain\Retailer\Filament\Pages\StoresPage as RetailerStoresPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('portal.login.show');
});


Route::get('/fix-retailer-login', function () {
    try {
        // 1. Create Role if missing
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'retailer',
            'guard_name' => 'retailer'
        ]);

        // 2. Find User
        $user = \App\Domain\User\Models\User::where('username', 'retailer')->first();

        if (!$user) {
            // Create if missing (optional, but requested context implies it exists)
            $user = \App\Domain\User\Models\User::create([
                'first_name' => 'Tienda',
                'last_name' => 'Demo',
                'username' => 'retailer',
                'email' => 'retailer@demo.com',
                'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
                'status' => 'active',
            ]);
        } else {
            // Reset password just in case
            $user->password_hash = \Illuminate\Support\Facades\Hash::make('password');
            $user->status = 'active';
            $user->save();
        }

        // 3. Assign Role
        // Force assignment for the specific guard
        $user->assignRole($role);

        return "LISTO. Usuario: retailer / Clave: password. Role 'retailer' (guard: retailer) asignado.";
    } catch (\Exception $e) {
        return "ERROR: " . $e->getMessage();
    }
});

Route::middleware('guest:admin,retailer')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login.show');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
});

Route::middleware('auth:admin')->group(function () {
    // Logout del panel de administraciИn (usa guard admin)
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Descargar PDF de redencion
    Route::get('/admin/redemptions/{redemption}/pdf', \App\Domain\Admin\Controllers\RedemptionPdfController::class)
        ->name('admin.redemptions.pdf');

    // Descargar PDF de liquidacion
    Route::get('/admin/liquidations/pdf/{period}/{storeId?}', \App\Domain\Admin\Controllers\LiquidationPdfController::class)
        ->name('admin.liquidations.pdf');

});

Route::middleware('auth:retailer')->group(function () {
    // Logout del portal de tiendas (usa guard retailer)
    Route::post('/portal/logout', [RetailerAuthController::class, 'logout'])->name('portal.logout');
    // Alias para Filament panel (ruta separada evita que se reemplacen nombres)
    Route::post('/portal/logout/filament', [RetailerAuthController::class, 'logout'])->name('filament.retailer.auth.logout');

    // Cambiar tienda activa del portal tendero
    Route::post('/portal/active-store', [ActiveStoreController::class, 'update'])->name('portal.active-store.update');

    // Detalle visual de un movimiento de saldo
    Route::get('/portal/movements/{movement}', BalanceMovementDetailController::class)
        ->name('portal.movement.show')
        ->middleware('panel:retailer');

    // Exportar un movimiento individual de saldo
    Route::get('/portal/movements/{movement}/export', BalanceMovementExportController::class)
        ->name('portal.movement.export');

    // ===== Redirecciones de legado a Filament (evita dashboards duplicados Blade) =====
    Route::prefix('tendero')->name('tendero.')->group(function () {
        Route::get('/', fn() => redirect()->to(PortalDashboard::getUrl(panel: 'retailer')))->name('index');
        Route::get('dashboard', fn() => redirect()->to(PortalDashboard::getUrl(panel: 'retailer')))->name('dashboard');
        Route::get('balance', fn() => redirect()->to(RetailerBalancePage::getUrl(panel: 'retailer')))->name('balance');
        Route::get('balance/{id}', fn() => redirect()->to(RetailerBalancePage::getUrl(panel: 'retailer')))->name('balance.show');
        Route::get('stores', fn() => redirect()->to(RetailerStoresPage::getUrl(panel: 'retailer')))->name('stores');
        Route::get('catalog', fn() => redirect()->to(StoreCatalogPage::getUrl(panel: 'retailer')))->name('catalog');
    });
});

Route::get('/debug-users', function () {
    return \App\Domain\User\Models\User::all()->map(function ($u) {
        return "ID: {$u->id} | User: {$u->username} | Email: {$u->email} | Status: {$u->status} | Roles: " . $u->getRoleNames()->implode(',');
    });
});

Route::middleware('guest:retailer,admin')->group(function () {
    Route::get('/portal/login', [RetailerAuthController::class, 'showLogin'])->name('portal.login.show');
    // Alias para Filament (ruta distinta para no sobreescribir el nombre principal)
    Route::get('/portal/login/filament', function () {
        return redirect()->route('portal.login.show');
    })->name('filament.retailer.auth.login');
    Route::post('/portal/login', [RetailerAuthController::class, 'login'])->name('portal.login.post');

    // ActivaciИn de cuenta (para retailers)
    Route::get('/portal/activate/{token}', [AccountActivationController::class, 'showActivationForm'])->name('portal.activate.show');
    Route::post('/portal/activate', [AccountActivationController::class, 'activate'])->name('portal.activate.post');
});

// Cambio forzado de contraseЃa (para usuarios autenticados)
Route::middleware('auth:admin,retailer')->group(function () {
    Route::get('/force-password-change', [PasswordChangeController::class, 'showForceChangeForm'])->name('password.force-change.show');
    Route::post('/force-password-change', [PasswordChangeController::class, 'forceChange'])->name('password.force-change.post');
});

Route::fallback(function () {
    if (request()->expectsJson()) {
        return response()->json([
            'message' => 'Page not found',
        ], 404);
    }
    return view('errors.404.404');
});

// FIX V2 - Robust
Route::get('/fix-retailer-login-v2', function () {
    try {
        \Illuminate\Support\Facades\DB::beginTransaction();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'retailer', 'guard_name' => 'retailer']);

        $user = \App\Domain\User\Models\User::where('username', 'retailer')->first();
        if (!$user) {
            $user = new \App\Domain\User\Models\User();
            $user->first_name = 'Tienda';
            $user->last_name = 'Demo';
            $user->username = 'retailer';
            $user->email = 'retailer@demo.com';
            $user->id_type = 'CC';
            $user->id_number = '123456789';
            $user->password_hash = \Illuminate\Support\Facades\Hash::make('password');
            $user->status = 'active';
            $user->save();
        } else {
            $user->password_hash = \Illuminate\Support\Facades\Hash::make('password');
            $user->status = 'active';
            if (!$user->id_number) {
                $user->id_type = 'CC';
                $user->id_number = '123456789';
            }
            $user->save();
        }
        $user->assignRole($role);

        \Illuminate\Support\Facades\DB::commit();

        return response()->json([
            'message' => 'LISTO V2. Usuario procesado correctamente.',
            'user' => $user->fresh(),
            'role_assigned' => $user->hasRole('retailer', 'retailer'),
            'role_in_db' => $role
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});
