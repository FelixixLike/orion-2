<?php

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Store\Models\BalanceMovement;
use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\LiquidationItem;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Iniciando limpieza de datos de liquidación...\n";

DB::transaction(function () {
    // 1. Desvincular reportes
    $affectedReports = OperatorReport::whereNotNull('liquidation_item_id')->update(['liquidation_item_id' => null]);
    echo "- Reportes actualizados (desvinculados): $affectedReports\n";

    // 2. Eliminar items de liquidación
    LiquidationItem::truncate();
    echo "- Tabla liquidation_items truncada.\n";

    // 3. Eliminar cabeceras de liquidación
    Liquidation::truncate();
    echo "- Tabla liquidations truncada.\n";

    // 4. Eliminar movimientos de balance
    $deletedMovements = BalanceMovement::where('movement_type', 'liquidation')->delete();
    echo "- Movimientos de balance eliminados: $deletedMovements\n";
});

echo "Limpieza completada con éxito.\n";
