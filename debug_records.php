<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Import\Models\OperatorReport;

$r = OperatorReport::where('period_label', '2025-07')->first();
if (!$r) {
    echo "No report found for 2025-07\n";
    exit;
}

$output = "";
$output .= "Report ID: " . $r->id . "\n";
$output .= "Simcard ID: " . $r->simcard_id . "\n";
$output .= "Period Year: " . $r->period_year . "\n";
$output .= "Period Month: " . $r->period_month . "\n";
$output .= "Cutoff Date: " . $r->cutoff_date . "\n";
$output .= "Liquidation Item ID: " . $r->liquidation_item_id . "\n";
$output .= "Is Consolidated: " . $r->is_consolidated . "\n";

$count = OperatorReport::where('period_label', '2025-07')->count();
$output .= "Total Reports: " . $count . "\n";

$nullPeriodYear = OperatorReport::where('period_label', '2025-07')->whereNull('period_year')->count();
$output .= "Null Period Year: " . $nullPeriodYear . "\n";

file_put_contents('debug_record_output.txt', $output);
