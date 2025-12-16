<?php

use Illuminate\Support\Facades\Cache;
use App\Domain\User\Models\User;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Assuming admin ID is 1. If multiple admins, we might need to guess or list all.
$userId = 1;
$period = '2025-07';

$progressKey = 'preview_progress_' . $userId . '_' . $period;
$resultKey = 'preview_data_' . $userId . '_' . $period;

echo "Checking keys for User $userId, Period $period\n";
echo "Progress Key ($progressKey): " . (Cache::get($progressKey) ?? 'NULL') . "\n";
echo "Result Key ($resultKey): " . (Cache::has($resultKey) ? 'EXISTS' : 'MISSING') . "\n";

// List all keys matching pattern if possible
try {
    $redis = Cache::store('redis')->connection();
    $keys = $redis->keys('*preview_progress*');
    echo "\nAll Progress Keys found in Redis:\n";
    foreach ($keys as $key) {
        echo " - " . $key . " => " . $redis->get(str_replace('orion_database_', '', $key)) . "\n"; // Prefix handling might be needed
    }
} catch (\Exception $e) {
    echo "Could not list keys via Redis directly: " . $e->getMessage();
}
