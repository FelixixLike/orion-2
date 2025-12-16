<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Import\Models\OperatorReport;

$period = '2025-07';

$total = OperatorReport::where('is_consolidated', 1)
    ->where('period_label', $period)
    ->count();

$nullSim = OperatorReport::where('is_consolidated', 1)
    ->where('period_label', $period)
    ->whereNull('simcard_id')
    ->count();

$validSim = OperatorReport::where('is_consolidated', 1)
    ->where('period_label', $period)
    ->whereNotNull('simcard_id')
    ->count();

$output = "Total Consolidated for $period: $total\n";
$output .= "NULL Simcard: $nullSim\n";
$output .= "VALID Simcard: $validSim\n";

file_put_contents('debug_counts.txt', $output);
echo $output;
