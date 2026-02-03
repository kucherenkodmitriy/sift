# Sift (sonic-php)

SIMD-accelerated JSON parsing for PHP via [sonic-rs](https://github.com/cloudwego/sonic-rs).

## Features

- **Lazy Parsing**: Extract specific values from massive JSON documents without full DOM construction
- **Query API**: Chainable, fluent interface that stays in Rust until hydration
- **SIMD Acceleration**: Uses modern CPU instructions for parallel JSON processing
- **Memory Efficient**: Zero-copy parsing minimizes allocations
- **Security Hardened**: Input size limits, depth limits, and overflow protection
- **PHP 8.x Compatible**: Works with PHP 8.1+

## Requirements

- PHP 8.1+ with development headers (`php-dev`)
- Rust 1.70+ (stable)
- Docker (recommended for development)

## Installation

### Using Docker (Recommended)

```bash
# Build and test in Docker
make docker-test

# Interactive development shell
make docker-shell
```

### Local Build

Requires PHP development headers installed:

```bash
# Ubuntu/Debian
sudo apt-get install php-dev

# Install cargo-php
cargo install cargo-php

# Build and install the extension
cargo php install --release

# Verify installation
php -m | grep sonic
```

### Composer (for IDE Support)

After installing the extension, add Composer support for IDE autocompletion:

```bash
composer require dmytrokucher/sift
```

This provides stub files for the `Sonic`, `Sift`, and `Query` classes, enabling full IDE autocompletion and type hints.

**Note**: Composer does not install the extension itself - you must build and install it first using one of the methods above.


## API Reference

### Legacy API (Sonic class)

#### `Sonic::get(string $json, string $pointer): mixed`

Extract a value by JSON pointer (RFC 6901) without full decode.

```php
$json = '{"users": [{"id": 1, "email": "alice@example.com"}]}';

// Extract specific value - no full parsing needed
$email = Sonic::get($json, '/users/0/email');
// Returns: "alice@example.com"
```

#### `Sonic::decode(string $json): mixed`

High-speed replacement for `json_decode()`.

```php
$data = Sonic::decode('{"name": "sonic", "fast": true}');
// Returns: ["name" => "sonic", "fast" => true]
```

#### `Sonic::isValid(string $json): bool`

SIMD-accelerated JSON validation.

```php
if (Sonic::isValid($userInput)) {
    $data = Sonic::decode($userInput);
}
```

### Modern API (Sift class)

The `Sift` class provides the same methods as `Sonic`, plus the Query API.

#### `Sift::query(string $json): Query`

Create a lazy Query object for chainable JSON navigation.

```php
$json = '{"users": [{"id": 1, "email": "alice@example.com", "active": true}]}';

// Chainable navigation - stays in Rust until hydration
$email = Sift::query($json)
    ->get('users')
    ->index(0)
    ->get('email')
    ->string();
// Returns: "alice@example.com"
```

### Query API Methods

#### Navigation (returns Query)

| Method | Description |
|--------|-------------|
| `pointer(string $ptr)` | Navigate using RFC 6901 JSON pointer |
| `get(string $key)` | Navigate into object by key |
| `index(int $idx)` | Navigate into array by index |

#### Hydration (extracts value)

| Method | Returns | Description |
|--------|---------|-------------|
| `string()` | `string` | Extract as string |
| `int()` | `int` | Extract as integer |
| `float()` | `float` | Extract as float |
| `bool()` | `bool` | Extract as boolean |
| `value()` | `mixed` | Full hydration to PHP array/value |
| `raw()` | `string` | Get raw JSON substring |

#### Type Checking

| Method | Returns | Description |
|--------|---------|-------------|
| `isNull()` | `bool` | Check if value is null |
| `isArray()` | `bool` | Check if value is array |
| `isObject()` | `bool` | Check if value is object |
| `getType()` | `string` | Get type as string |

### Query Example

```php
$json = '{"users": [{"id": 1, "profile": {"email": "alice@example.com"}}]}';

// Create query once, reuse for multiple extractions
$users = Sift::query($json)->get('users');

$email0 = $users->index(0)->get('profile')->get('email')->string();

// Using pointer syntax
$email = Sift::query($json)->pointer('/users/0/profile/email')->string();

// Type checking before extraction
$q = Sift::query($json)->pointer('/users/0');
if ($q->isObject()) {
    $user = $q->value();
}

// Get raw JSON without parsing
$rawUsers = Sift::query($json)->get('users')->raw();
// Returns: '[{"id": 1, "profile": {"email": "alice@example.com"}}]'
```

## JSON Pointer Syntax

JSON pointers follow RFC 6901:

| Pointer | Description |
|---------|-------------|
| `""` | Root document |
| `/foo` | Key "foo" at root |
| `/foo/0` | First element of array "foo" |
| `/foo/bar/baz` | Nested path |
| `/a~1b` | Key "a/b" (escaped slash) |
| `/a~0b` | Key "a~b" (escaped tilde) |

## Security

Sift includes several security hardening measures:

| Protection | Limit | Description |
|------------|-------|-------------|
| Input size | 64 MB | Maximum JSON input size |
| Nesting depth | 512 | Maximum nesting depth (same as PHP's json_decode) |
| Pointer segments | 256 | Maximum path segments in pointers |
| Integer overflow | Safe | Large u64 values convert to float instead of overflowing |
| Negative indices | Rejected | Negative array indices return an error |

## Benchmarks

Run benchmarks after installation:

```bash
make bench
```

### Expected Results

| Operation | json_decode | Sonic::decode | Sonic::get |
|-----------|-------------|---------------|------------|
| Full decode (10K records) | 100ms | 45ms | N/A |
| Extract single value | 100ms | 45ms | 5ms |
| Memory (50K records) | 150MB | 120MB | 1MB |

*Results vary by hardware and JSON structure*

## Development

```bash
# Run all tests
make test

# Run PHP tests only (requires extension installed)
make test-php

# Run tests in Docker
make docker-test

# Format code
make fmt

# Lint code
make lint
```

## Architecture

See [ARCHITECTURE.md](docs/ARCHITECTURE.md) for details on:

- Memory management between PHP's Zend Arena and Rust's ownership model
- Query API lazy evaluation design
- SIMD optimization strategies
- Security measures and limits
- Error handling across the FFI boundary

## Acknowledgements

This project builds on excellent open-source libraries:

- [sonic-rs](https://github.com/cloudwego/sonic-rs) - The SIMD-accelerated JSON engine (Apache-2.0)
- [ext-php-rs](https://github.com/davidcole1340/ext-php-rs) - PHP/Rust bindings (MIT/Apache-2.0)

See [NOTICE](NOTICE) file for full attribution.

## License

MIT License - see LICENSE file for details.
