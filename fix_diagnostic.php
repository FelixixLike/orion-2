<?php
use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$countNull = OperatorReport::whereNull('simcard_id')->count();
$countFixable = 0;

OperatorReport::whereNull('simcard_id')->chunk(500, function ($reports) use (&$countFixable) {
    foreach ($reports as $report) {
        // Try by ICCID
        $sim = null;
        if ($report->iccid) {
            $sim = Simcard::where('iccid', $report->iccid)->first();
        }
        if (!$sim && $report->phone_number) {
            $sim = Simcard::where('phone_number', $report->phone_number)->first();
        }

        if ($sim) {
            $countFixable++;
        }
    }
});

Log::info("FIX DIAGNOSTIC: Total Null Simcard_ID: $countNull. Fixable: $countFixable");
file_put_contents(__DIR__ . '/fix_diagnostic.txt', "Total Null Simcard_ID: $countNull. Fixable: $countFixable");
