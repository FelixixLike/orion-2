<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2025-09';
echo "--- DEBUG INFO FOR PERIOD $period ---\n";

$imports = App\Domain\Import\Models\Import::where('period', $period)->get();
echo "Imports found: " . $imports->count() . "\n";
foreach ($imports as $i) {
    $errorJson = json_encode($i->errors);
    echo "ID: {$i->id} | Type: {$i->type} | Status: {$i->status} | Rows: {$i->processed_rows}/{$i->total_rows} | Errors: {$errorJson}\n";
}

$reports = App\Domain\Import\Models\OperatorReport::where('period_year', 2025)->where('period_month', 9)->count();
echo "Total OperatorReports raw: $reports\n";

$consolidated = App\Domain\Import\Models\OperatorReport::where('period_year', 2025)->where('period_month', 9)->where('is_consolidated', true)->count();
echo "Total Consolidated: $consolidated\n";
