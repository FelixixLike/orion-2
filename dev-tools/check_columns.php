<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = [
    'operator_reports',
    'recharges',
    'sales_conditions',
];

echo "--- DATABASE COLUMNS ---\n";

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "\nTABLE: $table\n";
        echo "-----------------------\n";
        $columns = Schema::getColumnListing($table);
        foreach ($columns as $column) {
            $type = Schema::getColumnType($table, $column);
            echo "- $column ($type)\n";
        }
    } else {
        echo "\nTABLE: $table NOT FOUND\n";
    }
}

echo "\n--- END ---\n";
