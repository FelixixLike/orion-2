<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$simcardId = 24;
$rechargeId = 1;

$simcard = \App\Domain\Import\Models\Simcard::find($simcardId);
if (!$simcard) {
    die("Simcard $simcardId not found.\n");
}

$report = \App\Domain\Import\Models\OperatorReport::where('simcard_id', $simcardId)->first();
if (!$report) {
    die("No OperatorReport found for Simcard $simcardId.\n");
}

$recharge = \App\Domain\Import\Models\Recharge::find($rechargeId);
if (!$recharge) {
    // Create one if not exists
    $recharge = new \App\Domain\Import\Models\Recharge();
    $recharge->recharge_amount = 33500; // Value from screenshot example
    $recharge->import_id = 1; 
}

// Update Relationship
$recharge->simcard_id = $simcardId;

// Update Date to match Report
// Report has recharge_period (int month) and activation_date (date)
$year = $report->activation_date ? $report->activation_date->year : 2025;
$month = $report->recharge_period;

// Create a date for that month/year
$date = \Carbon\Carbon::create($year, $month, 15); // 15th of the month
$recharge->period_date = $date;

$recharge->save();

echo "UPDATED Recharge ID {$recharge->id}:\n";
echo " - Simcard ID: {$recharge->simcard_id}\n";
echo " - Amount: {$recharge->recharge_amount}\n";
echo " - Date: {$recharge->period_date->format('Y-m-d')} (Month: $month, Year: $year)\n";
echo " - Validating match for View...\n";

// Validation Logic same as View
$viewCheck = \App\Domain\Import\Models\Recharge::query()
    ->where('simcard_id', $simcardId)
    ->whereMonth('period_date', $month)
    ->whereYear('period_date', $year)
    ->sum('recharge_amount');

echo " - View Query Result Sum: $viewCheck\n";
