<?php

declare(strict_types=1);

/**
 * Cold Start Benchmark: json_decode vs Sonic::decode
 */

if (!class_exists('Sonic')) {
    die("ERROR: Sonic extension not loaded.\n");
}

// Generate test data
$sizes = [
    'small' => 100,
    'medium' => 10_000,
    'large' => 100_000,
];

$iterations = 100;

echo "Cold Start Benchmark: json_decode vs Sonic::decode\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($sizes as $label => $count) {
    $data = [];
    for ($i = 0; $i < $count; $i++) {
        $data[] = [
            'id' => $i,
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'active' => $i % 2 === 0,
        ];
    }
    $json = json_encode(['users' => $data]);
    $jsonSize = strlen($json);

    echo "Dataset: {$label} ({$count} records, " . number_format($jsonSize / 1024, 2) . " KB)\n";
    echo str_repeat("-", 60) . "\n";

    // Benchmark json_decode
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        json_decode($json, true);
    }
    $jsonDecodeTime = (hrtime(true) - $start) / 1_000_000; // ms

    // Benchmark Sonic::decode
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        Sonic::decode($json);
    }
    $sonicDecodeTime = (hrtime(true) - $start) / 1_000_000; // ms

    $speedup = $jsonDecodeTime / $sonicDecodeTime;

    printf("  json_decode:   %8.2f ms (%d iterations)\n", $jsonDecodeTime, $iterations);
    printf("  Sonic::decode: %8.2f ms (%d iterations)\n", $sonicDecodeTime, $iterations);
    printf("  Speedup:       %8.2fx\n\n", $speedup);
}
