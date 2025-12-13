<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2025-09';
[$year, $month] = explode('-', $period);

echo "--- DEBUG FOR 2025-09 ---\n";

$reports = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', false)
    ->get();

echo "Total Raw Rows: " . $reports->count() . "\n";
echo "Total Raw Sum (total_commission): " . $reports->sum('total_commission') . "\n";
echo "Total Raw Sum (80+20): " . $reports->sum(fn($r) => $r->commission_paid_80 + $r->commission_paid_20) . "\n";

$imports = $reports->pluck('import_id')->unique();
echo "Import IDs: " . $imports->implode(', ') . "\n";

foreach ($imports as $id) {
    $sub = $reports->where('import_id', $id);
    $count = $sub->count();
    $sum = $sub->sum('total_commission');
    echo "  -> Import $id: Rows $count, Sum $sum\n";
}

$consol = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', true)
    ->get();
echo "Consolidated Rows: " . $consol->count() . "\n";
echo "Consolidated Sum: " . $consol->sum('total_commission') . "\n";
