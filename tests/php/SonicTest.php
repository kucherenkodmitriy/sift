<?php

declare(strict_types=1);

/**
 * Basic tests for sonic-php extension.
 * Run with: php tests/php/SonicTest.php
 */

// Ensure extension is loaded
if (!class_exists('Sonic')) {
    die("ERROR: Sonic extension not loaded. Run 'cargo php install' first.\n");
}

echo "=== sonic-php Test Suite ===\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "✓ {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "✗ {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_true(bool $condition, string $message = ''): void {
    if (!$condition) {
        throw new Exception($message ?: 'Assertion failed');
    }
}

function assert_equals($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $msg = $message ?: sprintf(
            'Expected %s but got %s',
            var_export($expected, true),
            var_export($actual, true)
        );
        throw new Exception($msg);
    }
}

// Test data
$simpleJson = '{"name": "sonic", "version": 1, "active": true}';
$nestedJson = '{"users": [{"id": 1, "email": "alice@example.com"}, {"id": 2, "email": "bob@example.com"}]}';
$largeArray = json_encode(range(1, 1000));

// ==================== isValid tests ====================
echo "--- Sonic::isValid() ---\n";

test('validates correct JSON object', function() use ($simpleJson) {
    assert_true(Sonic::isValid($simpleJson));
});

test('validates correct JSON array', function() {
    assert_true(Sonic::isValid('[1, 2, 3]'));
});

test('rejects invalid JSON', function() {
    assert_true(!Sonic::isValid('not json'));
});

test('rejects malformed JSON', function() {
    assert_true(!Sonic::isValid('{"broken": }'));
});

// ==================== decode tests ====================
echo "\n--- Sonic::decode() ---\n";

test('decodes simple object', function() use ($simpleJson) {
    $result = Sonic::decode($simpleJson);
    assert_equals('sonic', $result['name']);
    assert_equals(1, $result['version']);
    assert_equals(true, $result['active']);
});

test('decodes nested object', function() use ($nestedJson) {
    $result = Sonic::decode($nestedJson);
    assert_equals(2, count($result['users']));
    assert_equals('alice@example.com', $result['users'][0]['email']);
});

test('decodes array', function() {
    $result = Sonic::decode('[1, 2, 3]');
    assert_equals([1, 2, 3], $result);
});

test('decodes null', function() {
    $result = Sonic::decode('null');
    assert_equals(null, $result);
});

test('decodes string', function() {
    $result = Sonic::decode('"hello"');
    assert_equals('hello', $result);
});

test('decodes number', function() {
    $result = Sonic::decode('42');
    assert_equals(42, $result);
});

test('decodes float', function() {
    $result = Sonic::decode('3.14');
    assert_equals(3.14, $result);
});

// ==================== get tests (Lazy Parsing) ====================
echo "\n--- Sonic::get() ---\n";

test('gets root value', function() use ($simpleJson) {
    $result = Sonic::get($simpleJson, '');
    assert_equals('sonic', $result['name']);
});

test('gets nested string', function() use ($nestedJson) {
    $email = Sonic::get($nestedJson, '/users/0/email');
    assert_equals('alice@example.com', $email);
});

test('gets nested integer', function() use ($nestedJson) {
    $id = Sonic::get($nestedJson, '/users/1/id');
    assert_equals(2, $id);
});

test('gets array by index', function() use ($nestedJson) {
    $user = Sonic::get($nestedJson, '/users/0');
    assert_equals(1, $user['id']);
    assert_equals('alice@example.com', $user['email']);
});

test('throws on missing key', function() use ($simpleJson) {
    try {
        Sonic::get($simpleJson, '/nonexistent');
        throw new Exception('Should have thrown');
    } catch (Exception $e) {
        assert_true(str_contains($e->getMessage(), 'not found'));
    }
});

test('throws on invalid pointer format', function() use ($simpleJson) {
    try {
        Sonic::get($simpleJson, 'no-leading-slash');
        throw new Exception('Should have thrown');
    } catch (Exception $e) {
        assert_true(str_contains($e->getMessage(), 'must start with'));
    }
});

// ==================== Summary ====================
echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);
