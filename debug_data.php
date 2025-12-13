<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$phone = '3027678717';
$iccid = '57101702410448932';

echo "--- DEBUG START ---\n";
echo "Searching for Phone: $phone\n";
echo "Searching for ICCID: $iccid\n";

// Check Simcard
$simcard = \App\Domain\Import\Models\Simcard::where('iccid', $iccid)->first();
if ($simcard) {
    echo "Simcard Found: ID {$simcard->id}, Phone {$simcard->phone_number}\n";
} else {
    echo "Simcard NOT Found for ICCID $iccid\n";
}

// Check ALL Recharges
$count = \App\Domain\Import\Models\Recharge::count();
echo "Total Recharges in DB: $count\n";

if ($count > 0) {
    echo "First 5 Recharges:\n";
    \App\Domain\Import\Models\Recharge::take(5)->get()->each(function($r){
        echo json_encode($r->toArray()) . "\n";
    });
}

// Check Sales Conditions for this Simcard
if ($simcard) {
    $sc = \App\Domain\Import\Models\SalesCondition::where('simcard_id', $simcard->id)->get();
    echo "SalesConditions for Simcard {$simcard->id}: " . $sc->count() . "\n";
    foreach($sc as $s) {
        echo json_encode($s->toArray()) . "\n";
    }
}
echo "--- DEBUG END ---\n";
