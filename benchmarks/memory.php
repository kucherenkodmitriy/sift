<?php

declare(strict_types=1);

/**
 * Memory Usage Benchmark
 *
 * Compares peak memory usage between different JSON parsing approaches.
 */

if (!class_exists('Sonic')) {
    die("ERROR: Sonic extension not loaded.\n");
}

function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return sprintf("%.2f %s", $bytes, $units[$i]);
}

// Generate large JSON
$userCount = 50_000;
$data = ['users' => []];

for ($i = 0; $i < $userCount; $i++) {
    $data['users'][] = [
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com",
        'data' => str_repeat("x", 100),
    ];
}

$json = json_encode($data);
$jsonSize = strlen($json);

echo "Memory Usage Benchmark\n";
echo str_repeat("=", 60) . "\n";
printf("JSON size: %s (%d users)\n\n", formatBytes($jsonSize), $userCount);

// Test 1: Full json_decode
gc_collect_cycles();
$memBefore = memory_get_usage(true);
$decoded = json_decode($json, true);
$memAfter = memory_get_peak_usage(true);
$jsonDecodeMemory = $memAfter - $memBefore;
unset($decoded);

echo "Full Decode Memory Usage:\n";
echo str_repeat("-", 60) . "\n";
printf("  json_decode:   %s\n", formatBytes($jsonDecodeMemory));

// Test 2: Full Sonic::decode
gc_collect_cycles();
$memBefore = memory_get_usage(true);
$decoded = Sonic::decode($json);
$memAfter = memory_get_peak_usage(true);
$sonicDecodeMemory = $memAfter - $memBefore;
unset($decoded);

printf("  Sonic::decode: %s\n\n", formatBytes($sonicDecodeMemory));

// Test 3: Lazy get (should use minimal memory)
gc_collect_cycles();
$memBefore = memory_get_usage(true);
$email = Sonic::get($json, '/users/25000/email');
$memAfter = memory_get_peak_usage(true);
$lazyGetMemory = $memAfter - $memBefore;

echo "Lazy Extraction Memory Usage:\n";
echo str_repeat("-", 60) . "\n";
printf("  Sonic::get:    %s\n", formatBytes($lazyGetMemory));
printf("  Value found:   %s\n\n", $email);

echo "Memory Savings with Lazy Parsing:\n";
echo str_repeat("-", 60) . "\n";
if ($jsonDecodeMemory > 0) {
    printf("  vs json_decode:   %.1f%% reduction\n",
        (1 - $lazyGetMemory / $jsonDecodeMemory) * 100);
}
if ($sonicDecodeMemory > 0) {
    printf("  vs Sonic::decode: %.1f%% reduction\n",
        (1 - $lazyGetMemory / $sonicDecodeMemory) * 100);
}
