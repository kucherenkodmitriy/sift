<?php

declare(strict_types=1);

namespace Sift;

/**
 * Query - Lazy JSON navigation API
 *
 * This is a stub file for IDE autocompletion. The actual implementation
 * is provided by the sonic PHP extension (written in Rust).
 *
 * Provides a chainable, fluent interface for navigating JSON documents.
 * All operations stay in Rust until a hydration method is called,
 * minimizing memory allocations and PHP/Rust boundary crossings.
 *
 * Security limits:
 * - Maximum input size: 64 MB
 * - Maximum path segments: 256
 * - Array indices must be non-negative
 *
 * @package Sift
 * @link https://github.com/dmytrokucher/sift
 */
class Query
{
    /**
     * Navigate using RFC 6901 JSON pointer.
     *
     * @param string $pointer JSON pointer path, e.g., "/users/0/email"
     * @return Query Returns new Query for method chaining
     * @throws \Exception If pointer format is invalid
     *
     * @example
     * $email = \Sift::query($json)->pointer('/users/0/email')->string();
     */
    public function pointer(string $pointer): Query
    {
    }

    /**
     * Navigate into object by key.
     *
     * @param string $key The object key to navigate to
     * @return Query Returns new Query for method chaining
     * @throws \Exception If path segment limit exceeded
     *
     * @example
     * $email = \Sift::query($json)->get('users')?->index(0)?->get('email')?->string();
     */
    public function get(string $key): Query
    {
    }

    /**
     * Navigate into array by index.
     *
     * @param int $index The array index (must be non-negative)
     * @return Query Returns new Query for method chaining
     * @throws \Exception If index is negative or path segment limit exceeded
     *
     * @example
     * $firstUser = \Sift::query($json)->get('users')?->index(0)?->value();
     */
    public function index(int $index): Query
    {
    }

    /**
     * Extract value as string.
     *
     * @return string The extracted string value
     * @throws \Exception If path not found or value is not a string
     *
     * @example
     * $email = \Sift::query($json)->get('email')?->string();
     */
    public function string(): string
    {
    }

    /**
     * Extract value as integer.
     *
     * @return int The extracted integer value
     * @throws \Exception If path not found or value is not an integer
     *
     * @example
     * $id = \Sift::query($json)->get('id')?->int();
     */
    public function int(): int
    {
    }

    /**
     * Extract value as float.
     *
     * @return float The extracted float value
     * @throws \Exception If path not found or value is not a float
     *
     * @example
     * $price = \Sift::query($json)->get('price')?->float();
     */
    public function float(): float
    {
    }

    /**
     * Extract value as boolean.
     *
     * @return bool The extracted boolean value
     * @throws \Exception If path not found or value is not a boolean
     *
     * @example
     * $active = \Sift::query($json)->get('active')?->bool();
     */
    public function bool(): bool
    {
    }

    /**
     * Full hydration to PHP array/value.
     *
     * Converts the current JSON value into a PHP native type (array, string, int, float, bool, or null).
     * This performs full parsing of the subtree at the current position.
     *
     * @return mixed The fully hydrated PHP value
     * @throws \Exception If path not found or value cannot be hydrated
     *
     * @example
     * $user = \Sift::query($json)->get('users')?->index(0)?->value();
     * // Returns: ["id" => 1, "email" => "alice@example.com", ...]
     */
    public function value(): mixed
    {
    }

    /**
     * Get raw JSON substring without parsing.
     *
     * Returns the raw JSON string at the current position without any parsing or validation.
     * Useful for extracting JSON subtrees to pass to other systems.
     *
     * @return string The raw JSON substring
     * @throws \Exception If path not found
     *
     * @example
     * $rawUsers = \Sift::query($json)->get('users')?->raw();
     * // Returns: '[{"id": 1, "email": "alice@example.com"}]'
     */
    public function raw(): string
    {
    }

    /**
     * Check if current value is null.
     *
     * @return bool True if value is null, false otherwise
     * @throws \Exception If path not found
     *
     * @example
     * if (\Sift::query($json)->get('optional')?->isNull()) {
     *     // Handle null case
     * }
     */
    public function isNull(): bool
    {
    }

    /**
     * Check if current value is an array.
     *
     * @return bool True if value is an array, false otherwise
     * @throws \Exception If path not found
     *
     * @example
     * if (\Sift::query($json)->get('items')?->isArray()) {
     *     $items = \Sift::query($json)->get('items')?->value();
     * }
     */
    public function isArray(): bool
    {
    }

    /**
     * Check if current value is an object.
     *
     * @return bool True if value is an object, false otherwise
     * @throws \Exception If path not found
     *
     * @example
     * if (\Sift::query($json)->get('user')?->isObject()) {
     *     $user = \Sift::query($json)->get('user')?->value();
     * }
     */
    public function isObject(): bool
    {
    }

    /**
     * Get the type of the current value as a string.
     *
     * @return string One of: "null", "boolean", "integer", "float", "string", "array", "object"
     * @throws \Exception If path not found
     *
     * @example
     * $type = \Sift::query($json)->get('field')?->getType();
     * // Returns: "string", "integer", "array", etc.
     */
    public function getType(): string
    {
    }
}
