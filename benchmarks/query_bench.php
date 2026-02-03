<?php

declare(strict_types=1);

/**
 * Query Benchmark: Comparing hydration strategies
 */

if (!class_exists('Sift')) {
    die("ERROR: Sift extension not loaded.\n");
}

// Generate test data
$userCount = 10000;
$data = ['users' => []];
for ($i = 0; $i < $userCount; $i++) {
    $data['users'][] = [
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com",
        'profile' => [
            'age' => 20 + ($i % 50),
            'city' => "City " . ($i % 100),
        ],
        'active' => $i % 2 === 0,
    ];
}
$json = json_encode($data);
$jsonSize = strlen($json) / 1024 / 1024;

$iterations = 1000;
$targetIndex = 5000;

echo "Query API Benchmark\n";
echo str_repeat("=", 60) . "\n";
echo "JSON size: " . number_format($jsonSize, 2) . " MB ({$userCount} users)\n";
echo "Iterations: {$iterations}\n";
echo "Target: user #{$targetIndex}\n\n";

// Benchmark 1: json_decode + access
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $decoded = json_decode($json, true);
    $email = $decoded['users'][$targetIndex]['email'];
}
$jsonDecodeTime = (hrtime(true) - $start) / 1_000_000;

// Benchmark 2: Sonic::get (immediate hydration)
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $email = Sonic::get($json, "/users/{$targetIndex}/email");
}
$sonicGetTime = (hrtime(true) - $start) / 1_000_000;

// Benchmark 3: Sift::query with pointer (lazy, stays in Rust)
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $email = Sift::query($json)
        ->pointer("/users/{$targetIndex}/email")
        ->string();
}
$siftPointerTime = (hrtime(true) - $start) / 1_000_000;

// Benchmark 4: Sift::query with chained get (lazy, stays in Rust)
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $email = Sift::query($json)
        ->get("users")
        ->index($targetIndex)
        ->get("email")
        ->string();
}
$siftChainTime = (hrtime(true) - $start) / 1_000_000;

// Benchmark 5: Reuse Query object (shows benefit of keeping cursor)
$start = hrtime(true);
$query = Sift::query($json)->get("users");
for ($i = 0; $i < $iterations; $i++) {
    $email = $query->index($targetIndex)->get("email")->string();
}
$siftReuseTime = (hrtime(true) - $start) / 1_000_000;

// Benchmark 6: Get raw JSON slice (no parsing at all)
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $userJson = Sift::query($json)
        ->pointer("/users/{$targetIndex}")
        ->raw();
}
$siftRawTime = (hrtime(true) - $start) / 1_000_000;

echo "Results:\n";
echo str_repeat("-", 60) . "\n";
printf("  json_decode + access:     %8.2f ms\n", $jsonDecodeTime);
printf("  Sonic::get (direct):      %8.2f ms\n", $sonicGetTime);
printf("  Sift::query->pointer:     %8.2f ms\n", $siftPointerTime);
printf("  Sift::query->get chain:   %8.2f ms\n", $siftChainTime);
printf("  Sift reuse Query:         %8.2f ms\n", $siftReuseTime);
printf("  Sift::query->raw:         %8.2f ms\n", $siftRawTime);
echo "\n";

echo "Speedup vs json_decode:\n";
echo str_repeat("-", 60) . "\n";
printf("  Sonic::get:               %8.2fx\n", $jsonDecodeTime / $sonicGetTime);
printf("  Sift::query->pointer:     %8.2fx\n", $jsonDecodeTime / $siftPointerTime);
printf("  Sift::query->get chain:   %8.2fx\n", $jsonDecodeTime / $siftChainTime);
printf("  Sift reuse Query:         %8.2fx\n", $jsonDecodeTime / $siftReuseTime);
printf("  Sift::query->raw:         %8.2fx\n", $jsonDecodeTime / $siftRawTime);
echo "\n";

// Verify correctness
echo "Verification:\n";
echo str_repeat("-", 60) . "\n";
$expected = "user{$targetIndex}@example.com";
$actual = Sift::query($json)->pointer("/users/{$targetIndex}/email")->string();
echo "  Expected: {$expected}\n";
echo "  Actual:   {$actual}\n";
echo "  Match:    " . ($expected === $actual ? "✓" : "✗") . "\n";