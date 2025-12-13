<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = [
    'imports',
    'simcards',
    'operator_reports',
    'recharges',
    'sales_conditions',
    'liquidations',
    'liquidation_items',
    'balance_movements',
    'stores',
    'users',
];

echo "=== ESTRUCTURA DE TABLAS ===\n\n";

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        echo "Tabla [$table] NO ENCONTRADA.\n\n";
        continue;
    }

    echo "TABLA: $table\n";
    echo str_repeat("-", 50) . "\n";
    echo sprintf("%-25s %-15s %-10s\n", "Columna", "Tipo", "Nulo");
    echo str_repeat("-", 50) . "\n";

    $columns = DB::select("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = ?
        ORDER BY ordinal_position
    ", [$table]);

    foreach ($columns as $column) {
        echo sprintf(
            "%-25s %-15s %-10s\n",
            $column->column_name,
            $column->data_type,
            $column->is_nullable
        );
    }
    echo "\n";
}
