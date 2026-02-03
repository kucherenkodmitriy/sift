<?php

declare(strict_types=1);

/**
 * Sift - Modern SIMD-accelerated JSON parsing API
 *
 * This is a stub file for IDE autocompletion. The actual implementation
 * is provided by the sonic PHP extension (written in Rust).
 *
 * Provides the same methods as Sonic, plus the modern Query API for
 * lazy JSON navigation with chainable method calls.
 *
 * @package Sift
 * @link https://github.com/dmytrokucher/sift
 */
class Sift
{
    /**
     * Create a lazy Query object for chainable JSON navigation.
     *
     * Returns a Query object that allows fluent, chainable navigation through
     * JSON documents. All operations stay in Rust until a hydration method
     * (string(), int(), value(), etc.) is called, minimizing memory usage.
     *
     * @param string $json The JSON string to query
     * @return \Sift\Query A Query object for chainable navigation
     *
     * @example
     * $json = '{"users": [{"id": 1, "email": "alice@example.com"}]}';
     *
     * // Chainable navigation
     * $email = Sift::query($json)
     *     ->get('users')
     *     ?->index(0)
     *     ?->get('email')
     *     ?->string();
     * // Returns: "alice@example.com"
     *
     * // Using pointer syntax
     * $email = Sift::query($json)->pointer('/users/0/email')->string();
     *
     * // Type checking before extraction
     * $q = Sift::query($json)->pointer('/users/0');
     * if ($q->isObject()) {
     *     $user = $q->value();
     * }
     *
     * // Get raw JSON without parsing
     * $rawUsers = Sift::query($json)->get('users')?->raw();
     */
    public static function query(string $json): \Sift\Query
    {
    }

    /**
     * Extract a value by JSON pointer (RFC 6901) without full decode.
     *
     * Uses lazy parsing to extract specific values from massive JSON documents
     * without constructing the full DOM, significantly reducing memory usage
     * and improving performance for targeted data extraction.
     *
     * Security limits:
     * - Maximum input size: 64 MB
     * - Maximum pointer segments: 256
     *
     * @param string $json The JSON string to parse
     * @param string $pointer JSON pointer path (RFC 6901), e.g., "/users/0/email"
     * @return mixed The extracted value (string, int, float, bool, array, or null)
     * @throws \Exception If JSON is invalid, pointer path not found, or limits exceeded
     *
     * @example
     * $json = '{"users": [{"id": 1, "email": "alice@example.com"}]}';
     * $email = Sift::get($json, '/users/0/email');
     * // Returns: "alice@example.com"
     */
    public static function get(string $json, string $pointer): mixed
    {
    }

    /**
     * High-speed replacement for json_decode().
     *
     * Decodes a JSON string into a PHP array using SIMD-accelerated parsing.
     * Significantly faster than native json_decode() for small to medium documents.
     *
     * Security limits:
     * - Maximum input size: 64 MB
     * - Maximum nesting depth: 512
     *
     * @param string $json The JSON string to decode
     * @return mixed The decoded value (array, string, int, float, bool, or null)
     * @throws \Exception If JSON is invalid or limits exceeded
     *
     * @example
     * $data = Sift::decode('{"name": "sonic", "fast": true}');
     * // Returns: ["name" => "sonic", "fast" => true]
     */
    public static function decode(string $json): mixed
    {
    }

    /**
     * SIMD-accelerated JSON validation.
     *
     * Validates JSON syntax. Returns false for invalid JSON or inputs
     * exceeding the 64 MB size limit.
     *
     * @param string $json The JSON string to validate
     * @return bool True if valid JSON, false otherwise
     *
     * @example
     * if (Sift::isValid($userInput)) {
     *     $data = Sift::decode($userInput);
     * }
     */
    public static function isValid(string $json): bool
    {
    }
}
