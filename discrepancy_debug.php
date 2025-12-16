<?php
use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Recharge;
use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2025-07';
list($year, $month) = explode('-', $period);

Log::error("--- DISCREPANCY DIAGNOSTIC START ($period) ---");

// 1. Check Operator Reports
$opCount = OperatorReport::where('period_year', $year)->where('period_month', $month)->count();
$opNullSim = OperatorReport::where('period_year', $year)->where('period_month', $month)->whereNull('simcard_id')->count();
Log::error("OP REPORTS: Total=$opCount, Null SimID=$opNullSim");

// 2. Check Recharges
$recCount = Recharge::where('period_label', $period)->count();
$recNullSim = Recharge::where('period_label', $period)->whereNull('simcard_id')->count();
Log::error("RECHARGES: Total=$recCount, Null SimID=$recNullSim");

// 3. Deep check: Do we have SIM mismatches?
// Grab a Recharge that is supposedly orphan
$reportsSimIds = OperatorReport::where('period_year', $year)->where('period_month', $month)->pluck('simcard_id')->filter()->toArray();
$orphanRecharge = Recharge::where('period_label', $period)
    ->whereNotIn('simcard_id', $reportsSimIds)
    ->whereNotNull('simcard_id')
    ->with('simcard')
    ->first();

if ($orphanRecharge) {
    Log::error("EXAMPLE ORPHAN RECHARGE: ID={$orphanRecharge->id}, SimID={$orphanRecharge->simcard_id}, ICCID={$orphanRecharge->simcard->iccid}, Phone={$orphanRecharge->phone_number}");

    // Does this ICCID exist in Operator Reports?
    $opMatch = OperatorReport::where('period_year', $year)
        ->where('period_month', $month)
        ->where(function ($q) use ($orphanRecharge) {
            $q->where('iccid', $orphanRecharge->simcard->iccid)
                ->orWhere('phone_number', $orphanRecharge->phone_number);
        })
        ->first();

    if ($opMatch) {
        Log::error("  -> FOUND MATCHING OP REPORT! ID={$opMatch->id}, SimID={$opMatch->simcard_id}, ICCID={$opMatch->iccid}");
        if ($opMatch->simcard_id != $orphanRecharge->simcard_id) {
            Log::error("  -> CRITICAL: SIM ID MISMATCH. Duplicate SIMs likely exist or inconsistent linking.");

            // Fix: Point OP Report to the SAME simcard as Recharge (assuming Recharge has the 'correct' recent one)
            // Actually, we should unify.
        } else {
            Log::error("  -> SimIDs match... why is it orphan? weird.");
        }
    } else {
        Log::error("  -> NO MATCHING OP REPORT FOUND for this ICCID/Phone in this period.");
    }
} else {
    Log::error("No orphans found with SimIDs. Maybe orphans have Null SimIDs?");
}

// 4. Force Relink Logic (Again, aggressive)
$affected = 0;
OperatorReport::where('period_year', $year)
    ->where('period_month', $month)
    ->whereNull('simcard_id')
    ->chunk(500, function ($rows) use (&$affected) {
        foreach ($rows as $row) {
            $sim = null;

            // Robust Match: Clean ICCID first
            $cleanIccid = \App\Domain\Import\Services\IccidCleanerService::clean($row->iccid);

            if ($cleanIccid)
                $sim = Simcard::where('iccid', $cleanIccid)->first();
            // Fallback to phone
            if (!$sim && $row->phone_number)
                $sim = Simcard::where('phone_number', $row->phone_number)->first();

            if ($sim) {
                $row->simcard_id = $sim->id;
                // Also ensure the report itself has the clean ICCID if it was missing 
                if (!$row->iccid || $row->iccid !== $cleanIccid) {
                    $row->iccid = $cleanIccid;
                }
                $row->save();
                $affected++;
            }
        }
    });

Log::error("RELINK UPDATE: Fixed $affected Operator Reports.");
Log::error("--- DISCREPANCY DIAGNOSTIC END ---");
