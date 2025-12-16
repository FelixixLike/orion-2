<?php

use App\Domain\Store\Models\BalanceMovement;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Iniciando limpieza TOTAL de importaciones y liquidaciones...\n";

// Disable safeguards might be needed inside models, but raw SQL is brute force.
// We use transaction for safety, though truncate acts immediately. 
// Actually TRUNCATE in Postgres is transaction-safe.

DB::transaction(function () {
    // 1. Limpiar dependencias de Store (Liquidaciones y sus items)
    
    // items
    DB::statement('TRUNCATE TABLE liquidation_items RESTART IDENTITY CASCADE');
    echo "- liquidation_items truncada.\n";
    
    // headers
    DB::statement('TRUNCATE TABLE liquidations RESTART IDENTITY CASCADE');
    echo "- liquidations truncada.\n";
    
    // movements (solo liquidaciones)
    BalanceMovement::where('movement_type', 'liquidation')->delete();
    echo "- BalanceMovement (liquidation) eliminados.\n";

    // 2. Limpiar DATOS IMPORTADOS (Hijos de Simcards)
    
    // reports
    DB::statement('TRUNCATE TABLE operator_reports RESTART IDENTITY CASCADE');
    echo "- operator_reports truncada.\n";
    
    // recharges
    DB::statement('TRUNCATE TABLE recharges RESTART IDENTITY CASCADE');
    echo "- recharges truncada.\n";
    
    // sales conditions
    DB::statement('TRUNCATE TABLE sales_conditions RESTART IDENTITY CASCADE');
    echo "- sales_conditions truncada.\n";

    // 3. Limpiar SIMCARDS (Padres)
    DB::statement('TRUNCATE TABLE simcards RESTART IDENTITY CASCADE');
    echo "- simcards truncada.\n";
    
    // 4. Limpiar registro de importaciones (Archivos subidos)
    // Asumiendo que la tabla se llama 'imports' por el listado anterior
    if (Schema::hasTable('imports')) {
        DB::statement('TRUNCATE TABLE imports RESTART IDENTITY CASCADE');
        echo "- imports truncada.\n";
    }

});

echo "Limpieza TOTAL completada.\n";
