<?php

declare(strict_types=1);

/**
 * Comprehensive tests for the Sift class and Query API.
 * Run with: php tests/php/SiftTest.php
 */

// Ensure extension is loaded
if (!class_exists('Sift')) {
    die("ERROR: Sift extension not loaded. Run 'cargo php install' first.\n");
}

echo "=== Sift & Query API Test Suite ===\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "\u{2713} {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "\u{2717} {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_true(bool $condition, string $message = ''): void {
    if (!$condition) {
        throw new Exception($message ?: 'Assertion failed: expected true');
    }
}

function assert_false(bool $condition, string $message = ''): void {
    if ($condition) {
        throw new Exception($message ?: 'Assertion failed: expected false');
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

function assert_throws(callable $fn, string $expectedMessage = ''): void {
    try {
        $fn();
        throw new Exception('Expected exception was not thrown');
    } catch (Exception $e) {
        if ($expectedMessage && !str_contains($e->getMessage(), $expectedMessage)) {
            throw new Exception("Exception message doesn't contain '$expectedMessage': " . $e->getMessage());
        }
    }
}

// ==================== Test Data ====================
$simpleJson = '{"name": "sift", "version": 1, "active": true, "ratio": 3.14}';
$nestedJson = '{"users": [{"id": 1, "email": "alice@example.com", "active": true}, {"id": 2, "email": "bob@example.com", "active": false}]}';
$typesJson = '{"string": "hello", "int": 42, "float": 3.14159, "bool": true, "null": null, "array": [1, 2, 3], "object": {"nested": "value"}}';
$deepJson = '{"a": {"b": {"c": {"d": {"e": "deep"}}}}}';
$unicodeJson = '{"greeting": "Hello, \u4e16\u754c!", "emoji": "\ud83d\ude00", "name": "Caf\u00e9"}';
$escapeJson = '{"path": "foo/bar", "tilde": "test~value", "both": "a~1b"}';
$largeArray = json_encode(range(0, 999));
$emptyStructures = '{"empty_array": [], "empty_object": {}}';

// ==================== Sift::isValid() ====================
echo "--- Sift::isValid() ---\n";

test('Sift::isValid - valid object', function() use ($simpleJson) {
    assert_true(Sift::isValid($simpleJson));
});

test('Sift::isValid - valid array', function() {
    assert_true(Sift::isValid('[1, 2, 3]'));
});

test('Sift::isValid - valid primitives', function() {
    assert_true(Sift::isValid('"string"'));
    assert_true(Sift::isValid('42'));
    assert_true(Sift::isValid('3.14'));
    assert_true(Sift::isValid('true'));
    assert_true(Sift::isValid('false'));
    assert_true(Sift::isValid('null'));
});

test('Sift::isValid - rejects invalid JSON', function() {
    assert_false(Sift::isValid('not json'));
    assert_false(Sift::isValid('{"broken": }'));
    assert_false(Sift::isValid('{missing: "quotes"}'));
    assert_false(Sift::isValid('[1, 2, ]'));
});

// ==================== Sift::decode() ====================
echo "\n--- Sift::decode() ---\n";

test('Sift::decode - simple object', function() use ($simpleJson) {
    $result = Sift::decode($simpleJson);
    assert_equals('sift', $result['name']);
    assert_equals(1, $result['version']);
    assert_equals(true, $result['active']);
    assert_equals(3.14, $result['ratio']);
});

test('Sift::decode - nested structures', function() use ($nestedJson) {
    $result = Sift::decode($nestedJson);
    assert_equals(2, count($result['users']));
    assert_equals('alice@example.com', $result['users'][0]['email']);
});

test('Sift::decode - all types', function() use ($typesJson) {
    $result = Sift::decode($typesJson);
    assert_equals('hello', $result['string']);
    assert_equals(42, $result['int']);
    assert_equals(3.14159, $result['float']);
    assert_equals(true, $result['bool']);
    assert_equals(null, $result['null']);
    assert_equals([1, 2, 3], $result['array']);
    assert_equals(['nested' => 'value'], $result['object']);
});

test('Sift::decode - empty structures', function() use ($emptyStructures) {
    $result = Sift::decode($emptyStructures);
    assert_equals([], $result['empty_array']);
    assert_equals([], $result['empty_object']);
});

test('Sift::decode - unicode', function() use ($unicodeJson) {
    $result = Sift::decode($unicodeJson);
    assert_true(str_contains($result['greeting'], 'Hello'));
    assert_equals('Café', $result['name']); // Unicode is preserved correctly
});

test('Sift::decode - throws on invalid JSON', function() {
    assert_throws(function() {
        Sift::decode('invalid json');
    });
});

// ==================== Sift::get() ====================
echo "\n--- Sift::get() ---\n";

test('Sift::get - root pointer', function() use ($simpleJson) {
    $result = Sift::get($simpleJson, '');
    assert_equals('sift', $result['name']);
});

test('Sift::get - nested path', function() use ($nestedJson) {
    $email = Sift::get($nestedJson, '/users/0/email');
    assert_equals('alice@example.com', $email);
});

test('Sift::get - array index', function() use ($largeArray) {
    $value = Sift::get($largeArray, '/500');
    assert_equals(500, $value);
});

test('Sift::get - deep nesting', function() use ($deepJson) {
    $value = Sift::get($deepJson, '/a/b/c/d/e');
    assert_equals('deep', $value);
});

test('Sift::get - throws on missing key', function() use ($simpleJson) {
    assert_throws(function() use ($simpleJson) {
        Sift::get($simpleJson, '/nonexistent');
    }, 'not found');
});

test('Sift::get - throws on invalid pointer', function() use ($simpleJson) {
    assert_throws(function() use ($simpleJson) {
        Sift::get($simpleJson, 'no-leading-slash');
    }, 'must start with');
});

// ==================== Sift::query() - Basic Creation ====================
echo "\n--- Sift::query() - Basic Creation ---\n";

test('Query creation', function() use ($simpleJson) {
    $q = Sift::query($simpleJson);
    assert_true($q instanceof \Sift\Query);
});

// ==================== Query::pointer() ====================
echo "\n--- Query::pointer() ---\n";

test('Query::pointer - root', function() use ($simpleJson) {
    $result = Sift::query($simpleJson)->pointer('')->value();
    assert_equals('sift', $result['name']);
});

test('Query::pointer - nested string', function() use ($nestedJson) {
    $email = Sift::query($nestedJson)->pointer('/users/0/email')->string();
    assert_equals('alice@example.com', $email);
});

test('Query::pointer - nested integer', function() use ($nestedJson) {
    $id = Sift::query($nestedJson)->pointer('/users/1/id')->int();
    assert_equals(2, $id);
});

test('Query::pointer - throws on invalid format', function() use ($simpleJson) {
    assert_throws(function() use ($simpleJson) {
        Sift::query($simpleJson)->pointer('invalid');
    }, 'must start with');
});

test('Query::pointer - RFC 6901 escape ~1 for slash', function() use ($escapeJson) {
    $value = Sift::query('{"foo/bar": "escaped"}')->pointer('/foo~1bar')->string();
    assert_equals('escaped', $value);
});

test('Query::pointer - RFC 6901 escape ~0 for tilde', function() {
    $value = Sift::query('{"test~value": "escaped"}')->pointer('/test~0value')->string();
    assert_equals('escaped', $value);
});

// ==================== Query::get() and Query::index() ====================
echo "\n--- Query::get() and Query::index() ---\n";

test('Query::get - single key', function() use ($simpleJson) {
    $name = Sift::query($simpleJson)->get('name')?->string();
    assert_equals('sift', $name);
});

test('Query::index - array element', function() use ($largeArray) {
    $value = Sift::query($largeArray)->index(100)?->int();
    assert_equals(100, $value);
});

test('Query::get + index chain', function() use ($nestedJson) {
    $email = Sift::query($nestedJson)
        ->get('users')
        ?->index(0)
        ?->get('email')
        ?->string();
    assert_equals('alice@example.com', $email);
});

test('Query chained navigation - deep', function() use ($deepJson) {
    $value = Sift::query($deepJson)
        ->get('a')
        ?->get('b')
        ?->get('c')
        ?->get('d')
        ?->get('e')
        ?->string();
    assert_equals('deep', $value);
});

test('Query::get - throws on missing key', function() use ($simpleJson) {
    assert_throws(function() use ($simpleJson) {
        Sift::query($simpleJson)->get('nonexistent')?->string();
    });
});

test('Query::index - throws on out of bounds', function() {
    assert_throws(function() {
        Sift::query('[1, 2, 3]')->index(999)?->int();
    });
});

test('Query::index - throws on negative index', function() {
    assert_throws(function() {
        Sift::query('[1, 2, 3]')->index(-1);
    }, 'non-negative');
});

// ==================== Query Hydration - string() ====================
echo "\n--- Query::string() ---\n";

test('Query::string - valid string', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('string')?->string();
    assert_equals('hello', $value);
});

test('Query::string - throws on non-string', function() use ($typesJson) {
    assert_throws(function() use ($typesJson) {
        Sift::query($typesJson)->get('int')?->string();
    }, 'not a string');
});

// ==================== Query Hydration - int() ====================
echo "\n--- Query::int() ---\n";

test('Query::int - valid integer', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('int')?->int();
    assert_equals(42, $value);
});

test('Query::int - throws on non-integer', function() use ($typesJson) {
    assert_throws(function() use ($typesJson) {
        Sift::query($typesJson)->get('string')?->int();
    }, 'not an integer');
});

// ==================== Query Hydration - float() ====================
echo "\n--- Query::float() ---\n";

test('Query::float - valid float', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('float')?->float();
    assert_equals(3.14159, $value);
});

test('Query::float - throws on non-float', function() use ($typesJson) {
    assert_throws(function() use ($typesJson) {
        Sift::query($typesJson)->get('string')?->float();
    }, 'not a float');
});

// ==================== Query Hydration - bool() ====================
echo "\n--- Query::bool() ---\n";

test('Query::bool - valid boolean true', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('bool')?->bool();
    assert_equals(true, $value);
});

test('Query::bool - valid boolean false', function() use ($nestedJson) {
    $value = Sift::query($nestedJson)->pointer('/users/1/active')->bool();
    assert_equals(false, $value);
});

test('Query::bool - throws on non-boolean', function() use ($typesJson) {
    assert_throws(function() use ($typesJson) {
        Sift::query($typesJson)->get('string')?->bool();
    }, 'not a boolean');
});

// ==================== Query Hydration - is_null() ====================
echo "\n--- Query::isNull() ---\n";

test('Query::isNull - returns true for null', function() use ($typesJson) {
    $isNull = Sift::query($typesJson)->get('null')?->isNull();
    assert_true($isNull);
});

test('Query::isNull - returns false for non-null', function() use ($typesJson) {
    $isNull = Sift::query($typesJson)->get('string')?->isNull();
    assert_false($isNull);
});

// ==================== Query Hydration - raw() ====================
echo "\n--- Query::raw() ---\n";

test('Query::raw - returns raw JSON string', function() use ($typesJson) {
    $raw = Sift::query($typesJson)->get('string')?->raw();
    assert_equals('"hello"', $raw);
});

test('Query::raw - returns raw JSON array', function() use ($typesJson) {
    $raw = Sift::query($typesJson)->get('array')?->raw();
    assert_equals('[1, 2, 3]', trim($raw));
});

test('Query::raw - returns raw JSON object', function() use ($typesJson) {
    $raw = Sift::query($typesJson)->get('object')?->raw();
    assert_true(str_contains($raw, 'nested'));
});

// ==================== Query Hydration - value() ====================
echo "\n--- Query::value() ---\n";

test('Query::value - hydrates to PHP array', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('array')?->value();
    assert_equals([1, 2, 3], $value);
});

test('Query::value - hydrates to PHP object/array', function() use ($typesJson) {
    $value = Sift::query($typesJson)->get('object')?->value();
    assert_equals('value', $value['nested']);
});

test('Query::value - hydrates nested structure', function() use ($nestedJson) {
    $user = Sift::query($nestedJson)->pointer('/users/0')->value();
    assert_equals(1, $user['id']);
    assert_equals('alice@example.com', $user['email']);
});

// ==================== Query Type Checking - isArray() ====================
echo "\n--- Query::isArray() ---\n";

test('Query::isArray - returns true for array', function() use ($typesJson) {
    $isArray = Sift::query($typesJson)->get('array')?->isArray();
    assert_true($isArray);
});

test('Query::isArray - returns false for non-array', function() use ($typesJson) {
    $isArray = Sift::query($typesJson)->get('string')?->isArray();
    assert_false($isArray);
});

test('Query::isArray - returns true for empty array', function() use ($emptyStructures) {
    $isArray = Sift::query($emptyStructures)->get('empty_array')?->isArray();
    assert_true($isArray);
});

// ==================== Query Type Checking - isObject() ====================
echo "\n--- Query::isObject() ---\n";

test('Query::isObject - returns true for object', function() use ($typesJson) {
    $isObject = Sift::query($typesJson)->get('object')?->isObject();
    assert_true($isObject);
});

test('Query::isObject - returns false for non-object', function() use ($typesJson) {
    $isObject = Sift::query($typesJson)->get('array')?->isObject();
    assert_false($isObject);
});

test('Query::isObject - returns true for empty object', function() use ($emptyStructures) {
    $isObject = Sift::query($emptyStructures)->get('empty_object')?->isObject();
    assert_true($isObject);
});

// ==================== Query Type Checking - getType() ====================
echo "\n--- Query::getType() ---\n";

test('Query::getType - string', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('string')?->getType();
    assert_equals('string', $type);
});

test('Query::getType - integer', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('int')?->getType();
    assert_equals('integer', $type);
});

test('Query::getType - float', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('float')?->getType();
    assert_equals('float', $type);
});

test('Query::getType - boolean', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('bool')?->getType();
    assert_equals('boolean', $type);
});

test('Query::getType - null', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('null')?->getType();
    assert_equals('null', $type);
});

test('Query::getType - array', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('array')?->getType();
    assert_equals('array', $type);
});

test('Query::getType - object', function() use ($typesJson) {
    $type = Sift::query($typesJson)->get('object')?->getType();
    assert_equals('object', $type);
});

// ==================== Query Reuse ====================
echo "\n--- Query Reuse ---\n";

test('Query can be reused for multiple extractions', function() use ($nestedJson) {
    $q = Sift::query($nestedJson)->get('users');

    $email0 = $q?->index(0)?->get('email')?->string();
    $email1 = $q?->index(1)?->get('email')?->string();

    assert_equals('alice@example.com', $email0);
    assert_equals('bob@example.com', $email1);
});

test('Query cloning preserves original', function() use ($simpleJson) {
    $q1 = Sift::query($simpleJson);
    $q2 = $q1->get('name');

    // q1 should still point to root
    $result = $q1->pointer('')->value();
    assert_equals('sift', $result['name']);

    // q2 should point to name
    $name = $q2?->string();
    assert_equals('sift', $name);
});

// ==================== Edge Cases ====================
echo "\n--- Edge Cases ---\n";

test('Large array traversal', function() use ($largeArray) {
    $last = Sift::query($largeArray)->index(999)?->int();
    assert_equals(999, $last);
});

test('Large number handling', function() {
    $json = '{"big": 9223372036854775807, "negative": -9223372036854775808}';
    $big = Sift::query($json)->get('big')?->int();
    assert_equals(9223372036854775807, $big);
});

test('Scientific notation', function() {
    $json = '{"sci": 1.5e10}';
    $value = Sift::query($json)->get('sci')?->float();
    assert_equals(1.5e10, $value);
});

test('Empty string key', function() {
    $json = '{"": "empty key value"}';
    $value = Sift::query($json)->get('')?->string();
    assert_equals('empty key value', $value);
});

test('Special characters in keys', function() {
    $json = '{"key with spaces": "value", "key\\nwith\\nnewlines": "value2"}';
    $value = Sift::query($json)->get('key with spaces')?->string();
    assert_equals('value', $value);
});

test('Numeric string keys', function() {
    $json = '{"123": "numeric key"}';
    $value = Sift::query($json)->get('123')?->string();
    assert_equals('numeric key', $value);
});

// ==================== Security Tests ====================
echo "\n--- Security Tests ---\n";

test('Large u64 numbers convert to float instead of overflowing', function() {
    // Number larger than i64::MAX (9223372036854775807)
    $json = '{"huge": 18446744073709551615}';
    $result = Sift::decode($json);
    // Should be a float, not a corrupted negative number
    assert_true($result['huge'] > 0, 'Large u64 should not overflow to negative');
});

// ==================== Summary ====================
echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);
