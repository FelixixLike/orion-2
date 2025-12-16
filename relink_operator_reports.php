<?php
use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = 0;
// Filter for 2025-07 specifically or all nulls? Let's do all NULLs to be safe.
OperatorReport::whereNull('simcard_id')->chunk(500, function ($reports) use (&$count) {
    foreach ($reports as $report) {
        $sim = null;
        $iccid = $report->iccid;

        if ($iccid) {
            // 1. Try Exact Match
            $sim = Simcard::where('iccid', $iccid)->first();

            // 2. Try Cleaned Match (Remove first 2 and last 1 digit - Standard logic)
            if (!$sim && strlen($iccid) > 15) {
                // Example: 8957...
                // Cleaner logic from Service: substr($iccid, 2, -1)
                $cleaned = substr($iccid, 2, -1);
                $sim = Simcard::where('iccid', $cleaned)->first();
            }

            // 3. Try "Like" match (last 10 digits?) - maybe too risky
        }

        if (!$sim && $report->phone_number) {
            $sim = Simcard::where('phone_number', $report->phone_number)->first();
        }

        if ($sim) {
            $report->simcard_id = $sim->id;
            $report->save();
            $count++;
        }
    }
});
Log::info("Relinked $count Operator Reports to Simcards using robust matching.");
echo "Relinked $count records.\n";
