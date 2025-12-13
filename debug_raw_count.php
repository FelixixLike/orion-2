<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$year = 2025;
$month = 9;

// Contar todos los registros RAW
$raw = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', false)
    ->get();

echo "--- DIAGNOSTICO ---\n";
echo "RAW Count: " . $raw->count() . "\n";
echo "RAW Sum: " . $raw->sum('total_commission') . "\n";
echo "Unique Sims in RAW: " . $raw->pluck('simcard_id')->unique()->count() . "\n";
echo "Import IDs present: " . $raw->pluck('import_id')->unique()->implode(', ') . "\n";

// Ver detalles de duplicados (si hay)
$grupos = $raw->groupBy('coid');
$duplicados = $grupos->filter(fn($g) => $g->count() > 1);
echo "Groups with duplicates (same COID): " . $duplicados->count() . "\n";

if ($raw->count() > 0) {
    echo "First RAW Import ID: " . $raw->first()->import_id . "\n";
}

// Ver lo que hay consolidado
$consolidated = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', true)
    ->get();

echo "CONSOLIDATED Count: " . $consolidated->count() . "\n";
echo "CONSOLIDATED Sum: " . $consolidated->sum('total_commission') . "\n";
