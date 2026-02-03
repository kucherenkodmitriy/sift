<?php

declare(strict_types=1);

/**
 * Lazy Extraction Benchmark: Full decode vs Sonic::get
 *
 * Demonstrates the advantage of lazy parsing when extracting a single value
 * from a large JSON document.
 */

if (!class_exists('Sonic')) {
    die("ERROR: Sonic extension not loaded.\n");
}

// Generate a large nested JSON structure
$userCount = 10_000;
$data = ['users' => []];

for ($i = 0; $i < $userCount; $i++) {
    $data['users'][] = [
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com",
        'profile' => [
            'bio' => str_repeat("Lorem ipsum dolor sit amet. ", 10),
            'avatar' => "https://example.com/avatars/{$i}.jpg",
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
                'language' => 'en',
            ],
        ],
    ];
}

$json = json_encode($data);
$jsonSize = strlen($json);
$iterations = 1000;

echo "Lazy Extraction Benchmark\n";
echo str_repeat("=", 60) . "\n";
printf("JSON size: %.2f MB (%d users)\n", $jsonSize / 1_048_576, $userCount);
printf("Iterations: %d\n", $iterations);
printf("Target: /users/5000/email\n\n");

// Method 1: Full decode + array access
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $decoded = json_decode($json, true);
    $email = $decoded['users'][5000]['email'];
}
$fullDecodeTime = (hrtime(true) - $start) / 1_000_000;

// Method 2: Sonic full decode + array access
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $decoded = Sonic::decode($json);
    $email = $decoded['users'][5000]['email'];
}
$sonicDecodeTime = (hrtime(true) - $start) / 1_000_000;

// Method 3: Sonic lazy get (no full decode)
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $email = Sonic::get($json, '/users/5000/email');
}
$lazyGetTime = (hrtime(true) - $start) / 1_000_000;

echo "Results:\n";
echo str_repeat("-", 60) . "\n";
printf("  json_decode + access:  %8.2f ms\n", $fullDecodeTime);
printf("  Sonic::decode + access:%8.2f ms\n", $sonicDecodeTime);
printf("  Sonic::get (lazy):     %8.2f ms\n", $lazyGetTime);
echo "\n";
printf("  Lazy speedup vs json_decode:   %.2fx\n", $fullDecodeTime / $lazyGetTime);
printf("  Lazy speedup vs Sonic::decode: %.2fx\n", $sonicDecodeTime / $lazyGetTime);
