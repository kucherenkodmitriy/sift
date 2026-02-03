<?php

declare(strict_types=1);

/**
 * Enhanced Memory Usage Benchmark
 *
 * Measures BOTH:
 * 1. PHP heap memory (memory_get_usage) - Shows PHP array allocation savings
 * 2. Process RSS (Resident Set Size) - Shows total memory including Rust allocations
 *
 * This gives the complete picture of memory usage across the PHP/Rust boundary.
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

/**
 * Get process RSS (Resident Set Size) in bytes.
 * This includes ALL memory: PHP heap + Rust allocations + everything else.
 */
function getProcessRSS(): int {
    // Try /proc/self/status (Linux, works in Docker)
    if (file_exists('/proc/self/status')) {
        $status = file_get_contents('/proc/self/status');
        if (preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches)) {
            return (int)$matches[1] * 1024; // Convert KB to bytes
        }
    }
    
    // Fallback: getrusage (cross-platform, but less accurate)
    $rusage = getrusage();
    if (isset($rusage['ru_maxrss'])) {
        // On Linux: KB, on macOS: bytes
        $maxrss = $rusage['ru_maxrss'];
        return PHP_OS_FAMILY === 'Darwin' ? $maxrss : $maxrss * 1024;
    }
    
    return 0;
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

echo "Enhanced Memory Usage Benchmark\n";
echo str_repeat("=", 70) . "\n";
printf("JSON size: %s (%d users)\n", formatBytes($jsonSize), $userCount);
echo "Measuring: PHP heap + Process RSS (total memory)\n\n";

// Baseline measurement
gc_collect_cycles();
sleep(1); // Let system stabilize
$baselineRSS = getProcessRSS();

// Test 1: Full json_decode
echo "Test 1: json_decode() - Full Parse\n";
echo str_repeat("-", 70) . "\n";

gc_collect_cycles();
$phpBefore = memory_get_usage(true);
$rssBefore = getProcessRSS();

$decoded = json_decode($json, true);

$phpAfter = memory_get_peak_usage(true);
$rssAfter = getProcessRSS();

$jsonDecodePhp = $phpAfter - $phpBefore;
$jsonDecodeRss = $rssAfter - $rssBefore;

printf("  PHP heap:    %s\n", formatBytes($jsonDecodePhp));
printf("  Process RSS: %s\n", formatBytes($jsonDecodeRss));
unset($decoded);

// Test 2: Full Sonic::decode
echo "\nTest 2: Sonic::decode() - Full Parse\n";
echo str_repeat("-", 70) . "\n";

gc_collect_cycles();
$phpBefore = memory_get_usage(true);
$rssBefore = getProcessRSS();

$decoded = Sonic::decode($json);

$phpAfter = memory_get_peak_usage(true);
$rssAfter = getProcessRSS();

$sonicDecodePhp = $phpAfter - $phpBefore;
$sonicDecodeRss = $rssAfter - $rssBefore;

printf("  PHP heap:    %s\n", formatBytes($sonicDecodePhp));
printf("  Process RSS: %s\n", formatBytes($sonicDecodeRss));
unset($decoded);

// Test 3: Lazy get (should use minimal memory on both metrics)
echo "\nTest 3: Sonic::get() - Lazy Extraction\n";
echo str_repeat("-", 70) . "\n";

gc_collect_cycles();
$phpBefore = memory_get_usage(true);
$rssBefore = getProcessRSS();

$email = Sonic::get($json, '/users/25000/email');

$phpAfter = memory_get_peak_usage(true);
$rssAfter = getProcessRSS();

$lazyGetPhp = $phpAfter - $phpBefore;
$lazyGetRss = $rssAfter - $rssBefore;

printf("  PHP heap:    %s\n", formatBytes($lazyGetPhp));
printf("  Process RSS: %s\n", formatBytes($lazyGetRss));
printf("  Value found: %s\n", $email);

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "Memory Savings with Lazy Parsing (Sonic::get)\n";
echo str_repeat("=", 70) . "\n";

echo "\nPHP Heap Savings:\n";
if ($jsonDecodePhp > 0) {
    printf("  vs json_decode:   %s saved (%.1f%% reduction)\n",
        formatBytes($jsonDecodePhp - $lazyGetPhp),
        (1 - $lazyGetPhp / $jsonDecodePhp) * 100);
}
if ($sonicDecodePhp > 0) {
    printf("  vs Sonic::decode: %s saved (%.1f%% reduction)\n",
        formatBytes($sonicDecodePhp - $lazyGetPhp),
        (1 - $lazyGetPhp / $sonicDecodePhp) * 100);
}

echo "\nTotal Process Memory Savings (including Rust):\n";
if ($jsonDecodeRss > 0) {
    printf("  vs json_decode:   %s saved (%.1f%% reduction)\n",
        formatBytes($jsonDecodeRss - $lazyGetRss),
        (1 - $lazyGetRss / $jsonDecodeRss) * 100);
}
if ($sonicDecodeRss > 0) {
    printf("  vs Sonic::decode: %s saved (%.1f%% reduction)\n",
        formatBytes($sonicDecodeRss - $lazyGetRss),
        (1 - $lazyGetRss / $sonicDecodeRss) * 100);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Explanation:\n";
echo "  • PHP heap:    Memory tracked by PHP (arrays, strings, objects)\n";
echo "  • Process RSS: Total memory including Rust allocations\n";
echo "  • Lazy parsing avoids creating PHP arrays AND Rust DOM structures\n";
echo str_repeat("=", 70) . "\n";
