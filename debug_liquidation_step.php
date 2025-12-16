<?php

use App\Domain\Import\Models\OperatorReport;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2025-07';
[$year, $month] = explode('-', $period);

echo "Checking for period: $period ($year-$month)\n";

$query = OperatorReport::query()
    ->where('is_consolidated', true)
    ->where(function ($q) use ($year, $month, $period) {
        $q->where(function ($sub) use ($year, $month) {
            $sub->where('period_year', $year)->where('period_month', $month);
        })
            ->orWhere('period_label', $period)
            ->orWhere(function ($sub) use ($year, $month) {
                $sub->whereNull('period_year')
                    ->whereYear('cutoff_date', $year)
                    ->whereMonth('cutoff_date', $month);
            });
    })
    ->whereNull('liquidation_item_id');

$count = $query->count();
echo "Found $count records to liquidate.\n";

if ($count > 0) {
    $start = microtime(true);
    $ids = $query->pluck('id')->toArray();
    $end = microtime(true);
    echo "Pluck took " . ($end - $start) . " seconds.\n";
    echo "First 10 IDs: " . implode(', ', array_slice($ids, 0, 10)) . "\n";
}
