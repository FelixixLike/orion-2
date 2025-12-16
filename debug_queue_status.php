<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Current server time: " . date('Y-m-d H:i:s') . "\n";

$jobs = DB::table('jobs')->get();
echo "Pending Jobs in DB: " . $jobs->count() . "\n";
foreach ($jobs as $job) {
    echo " - ID: {$job->id}, Queue: {$job->queue}, Payload: " . substr($job->payload, 0, 100) . "...\n";
}

$failed = DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get();
echo "\nLast 5 Failed Jobs:\n";
foreach ($failed as $job) {
    echo " - ID: {$job->id}, Failed At: {$job->failed_at}, Exception: " . substr($job->exception, 0, 100) . "...\n";
}
