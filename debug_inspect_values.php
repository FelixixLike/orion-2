<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$year = 2025;
$month = 9;

// Tomar un COID que tenga duplicados
$duplicates = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', false)
    ->select('coid')
    ->groupBy('coid')
    ->havingRaw('count(*) > 1')
    ->limit(1)
    ->pluck('coid');

if ($duplicates->isEmpty()) {
    echo "No duplicates found to inspect.\n";
    exit;
}

$coid = $duplicates->first();
echo "Inspecting COID: $coid\n";

$records = App\Domain\Import\Models\OperatorReport::where('coid', $coid)
    ->where('is_consolidated', false)
    ->get();

foreach ($records as $r) {
    echo "ID: {$r->id} | Import: {$r->import_id} | Comm80: {$r->commission_paid_80} | Comm20: {$r->commission_paid_20} | Total: {$r->total_commission}\n";
}

// Check imports summary
$imports = App\Domain\Import\Models\OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->where('is_consolidated', false)
    ->selectRaw('import_id, count(*) as count, sum(total_commission) as total')
    ->groupBy('import_id')
    ->get();

foreach ($imports as $i) {
    echo "Import ID: {$i->import_id} | Count: {$i->count} | Total Commission: {$i->total}\n";
}
