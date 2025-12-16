<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking for job_batches table...\n";
if (Schema::hasTable('job_batches')) {
    echo "Table 'job_batches' exists.\n";
} else {
    echo "Table 'job_batches' MISSING!\n";
    exit(1);
}

echo "Testing Bus::batch dispatch...\n";

try {
    $batch = Bus::batch([
        function () {
            echo "Job ran\n";
        }
    ])->dispatch();

    echo "Batch dispatched with ID: " . $batch->id . "\n";
} catch (\Throwable $e) {
    echo "Error dispatching batch: " . $e->getMessage() . "\n";
}
