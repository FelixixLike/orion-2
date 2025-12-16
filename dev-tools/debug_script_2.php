<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$importId = 13;
echo "--- DEBUG INFO FOR IMPORT ID $importId ---\n";

$count = App\Domain\Import\Models\OperatorReport::where('import_id', $importId)->count();
echo "Records for Import $importId: $count\n";

if ($count > 0) {
    $first = App\Domain\Import\Models\OperatorReport::where('import_id', $importId)->first();
    echo "First record period: {$first->period_year}-{$first->period_month}\n";
    echo "First record cutoff: {$first->cutoff_date}\n";
    echo "Consolidated: " . ($first->is_consolidated ? 'YES' : 'NO') . "\n";
} else {
    echo "No records found for this import ID.\n";
}

$all = App\Domain\Import\Models\OperatorReport::count();
echo "Total OperatorReports in DB (any period): $all\n";
