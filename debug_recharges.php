<?php

use App\Domain\Import\Models\Recharge;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2025-07';
[$year, $month] = explode('-', $period);

echo "Checking Recharges for period: $period ($year-$month)\n";

$query = Recharge::query()
    ->where(function ($query) use ($period, $year, $month) {
        $query->where('period_label', $period)
            ->orWhere(function ($sub) use ($year, $month) {
                $sub->whereYear('period_date', $year)
                    ->whereMonth('period_date', $month);
            });
    });

$count = $query->count();
echo "Found $count Recharges.\n";
