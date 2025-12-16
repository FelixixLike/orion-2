<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$simcardId = 24;

$salesCondition = \App\Domain\Import\Models\SalesCondition::where('simcard_id', $simcardId)->latest('period_date')->first();
$report = \App\Domain\Import\Models\OperatorReport::where('simcard_id', $simcardId)->first();

$comm = $salesCondition?->commission_percentage ?? 0;
$pay = $report?->payment_percentage ?? 0;

echo "Commission %: $comm\n";
echo "Payment %: $pay\n";

if ($pay != 0) {
    $div = $comm / $pay;
    echo "Division (Comm/Pay): $div\n";
    echo "Formatted (0 decimals): " . number_format($div, 0) . "%\n";
    echo "As Percentage ((Comm/Pay)*100): " . number_format($div * 100, 0) . "%\n";
} else {
    echo "Payment % is 0, cannot divide.\n";
}
