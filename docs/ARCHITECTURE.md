# Architecture

This document explains the technical architecture of Sift (sonic-php), focusing on memory management, the Query API, SIMD optimization, security measures, and the PHP/Rust interop layer.

## Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     PHP Application                          │
├─────────────────────────────────────────────────────────────┤
│              Sonic / Sift PHP Classes                        │
│              (ext-php-rs generated bindings)                 │
├─────────────────────────────────────────────────────────────┤
│     lib.rs          │    query.rs        │    errors.rs      │
│  (API entry points) │  (Query builder)   │  (Error types)    │
├─────────────────────────────────────────────────────────────┤
│                     parser.rs                                │
│              (Rust wrapper functions)                        │
├─────────────────────────────────────────────────────────────┤
│                     sonic-rs                                 │
│              (SIMD JSON engine)                              │
└─────────────────────────────────────────────────────────────┘
```

## Module Structure

| Module | Purpose |
|--------|---------|
| `lib.rs` | PHP module registration, `Sonic` and `Sift` class definitions |
| `query.rs` | Lazy `Query` builder with path accumulation |
| `parser.rs` | Core parsing logic, type conversion, security validation |
| `errors.rs` | Error types and PHP exception mapping |

## Memory Management

### The Challenge

PHP uses a reference-counted garbage collector with the Zend Memory Manager (ZMM), while Rust uses compile-time ownership with RAII. Bridging these two models requires careful attention to memory lifetimes.

### Strategy: Copy at Boundaries

Sift uses a **copy-at-boundary** strategy:

1. **PHP → Rust**: String data is borrowed (`&str`) when possible, avoiding copies during parsing
2. **Rust → PHP**: Parsed values are converted to PHP types (`Zval`) which are managed by ZMM

```rust
// Zero-copy borrow from PHP
pub fn decode(json: &str) -> Result<Zval, SonicError> {
    let value: Value = sonic_rs::from_str(json)?;  // Borrows json
    value_to_zval(&value)                           // Copies to PHP heap
}
```

### Query API Memory Model

The `Query` struct uses `Arc<String>` to share the JSON input across multiple navigation calls:

```rust
pub struct Query {
    json: Arc<String>,        // Shared JSON (zero-copy clones)
    path: Vec<PathSegment>,   // Accumulated path segments
}
```

- **Navigation methods** (`get()`, `index()`, `pointer()`) clone the `Arc`, not the JSON
- **Hydration methods** (`string()`, `value()`, etc.) resolve the path and extract data
- Path accumulation has zero cost until hydration

### Lazy Parsing Memory Model

The `Sonic::get()` and `Query` API minimize memory allocation by:

1. Parsing only the structural tokens needed to navigate to the target
2. Allocating only the final extracted value
3. Never constructing intermediate PHP arrays/objects

```
Traditional: Parse 100MB → Build 150MB PHP array → Extract 100 bytes
Lazy:        Scan 100MB → Extract 100 bytes → Allocate 100 bytes
```

## Query API Design

### Lazy Evaluation Pattern

The Query API implements a lazy evaluation pattern:

```php
$q = Sift::query($json)   // Create Query, no parsing
    ->get('users')         // Accumulate path segment
    ->index(0)             // Accumulate path segment
    ->get('email');        // Accumulate path segment

$email = $q->string();     // NOW: resolve path + extract value
```

### Path Segment Types

```rust
enum PathSegment {
    Key(FastStr),    // Object key navigation
    Index(usize),    // Array index navigation
}
```

### Resolution Flow

```
Query::string()
    └─> resolve()
        └─> Build PointerNode[] from path segments
            └─> sonic_rs::get(json, nodes)
                └─> LazyValue
                    └─> Extract string value
```

## SIMD Acceleration

### How sonic-rs Uses SIMD

sonic-rs leverages CPU vector instructions to process multiple bytes simultaneously:

- **AVX2** (256-bit): Process 32 characters at once
- **SSE4.2** (128-bit): Fallback for older CPUs
- **NEON** (ARM): Support for ARM64 platforms

### Key Optimizations

1. **Structural Character Detection**: SIMD finds `{}[],:\"` in parallel
2. **String Validation**: UTF-8 validation using vector operations
3. **Number Parsing**: SIMD-accelerated integer/float parsing
4. **Whitespace Skipping**: Vector comparison skips spaces efficiently

### Example: Finding a Key

When executing `Sonic::get($json, "/users/500/email")`:

```
1. SIMD scan for "users" key
2. Skip array opening, count to index 500
3. SIMD scan for "email" key
4. Extract value (no further parsing)
```

## Security Measures

### Input Validation

Sift implements multiple layers of security protection:

| Protection | Constant | Value | Purpose |
|------------|----------|-------|---------|
| Input size limit | `MAX_INPUT_SIZE` | 64 MB | Prevent memory exhaustion |
| Nesting depth limit | `MAX_DEPTH` | 512 | Prevent stack overflow |
| Pointer segment limit | `MAX_POINTER_SEGMENTS` | 256 | Prevent DoS via long paths |

### Integer Overflow Protection

Large unsigned integers that exceed `i64::MAX` are safely converted to floats:

```rust
if n <= i64::MAX as u64 {
    (n as i64).set_zval(...)
} else {
    // Convert to float to preserve value instead of silent overflow
    (n as f64).set_zval(...)
}
```

### Negative Index Validation

Array indices must be non-negative:

```rust
pub fn index(&self, idx: i64) -> Result<Query, SonicError> {
    if idx < 0 {
        return Err(SonicError::InvalidPointer(
            "Array index must be non-negative"
        ));
    }
    // ...
}
```

### Error Message Sanitization

Error messages avoid leaking internal details or user-provided paths:

```rust
// Before (information leak)
SonicError::KeyNotFound(format!("Path not found: {}", path))

// After (sanitized)
SonicError::KeyNotFound("Path not found".to_string())
```

### Logging Security

User-provided data is not logged to prevent sensitive information exposure:

```rust
// Avoid logging user-provided paths
log::debug!("Sonic::get called");  // Don't log the pointer
```

## Error Handling

### Error Flow

```
sonic-rs Error → SonicError (Rust) → PhpException → PHP Exception
```

### Error Types

| Rust Error | PHP Exception | Description |
|------------|---------------|-------------|
| `ParseError` | `Exception` | Invalid JSON syntax |
| `InvalidPointer` | `Exception` | Malformed JSON pointer or invalid index |
| `KeyNotFound` | `Exception` | Path doesn't exist |
| `TypeError` | `Exception` | Type conversion failed |
| `IoError` | `Exception` | I/O operation failed |

### Implementation

```rust
// errors.rs
impl From<SonicError> for PhpException {
    fn from(err: SonicError) -> Self {
        PhpException::new(err.to_string(), 0, ce::exception())
    }
}
```

## ext-php-rs Integration

### How PHP Calls Rust

ext-php-rs generates the PHP extension boilerplate:

1. `#[php_module]` registers the extension with Zend
2. `#[php_class]` creates a PHP class
3. `#[php_static]` exposes static methods
4. Return types are automatically converted

### Thread Safety

PHP traditionally runs in a single-threaded model (per-request). Sift:

- Uses no global mutable state
- All operations are request-scoped
- Rust's ownership prevents data races

## Performance Characteristics

### Time Complexity

| Operation | Complexity | Notes |
|-----------|------------|-------|
| `decode()` | O(n) | Full document scan |
| `get()` | O(n) worst, O(k) typical | k = path depth |
| `isValid()` | O(n) | Full syntax validation |
| `Query::get/index` | O(1) | Path accumulation only |
| `Query::string/value` | O(n) worst, O(k) typical | Resolution + extraction |

### Space Complexity

| Operation | Complexity | Notes |
|-----------|------------|-------|
| `decode()` | O(n) | Full DOM in memory |
| `get()` | O(1) | Only result value |
| `isValid()` | O(1) | No allocation |
| `Query` navigation | O(p) | p = path length |
| `Query::value()` | O(m) | m = subtree size |

### Depth-Limited Recursion

Both `lazyvalue_to_zval` and `value_to_zval` track recursion depth to prevent stack overflow:

```rust
fn value_to_zval_with_depth(value: &Value, depth: usize) -> Result<Zval, SonicError> {
    if depth > MAX_DEPTH {
        return Err(SonicError::ParseError("Maximum nesting depth exceeded"));
    }
    // ... recursive calls pass depth + 1
}
```

## Future Considerations

### Stream Parsing

Planned support for PHP streams:

```php
$stream = fopen('large.json', 'r');
$value = Sonic::getFromStream($stream, '/data/0');
```

### Async Support

Integration with PHP 8.1+ Fibers for non-blocking parsing.

### Custom Allocator

Potential integration with Zend Memory Manager for reduced copy overhead.

### Configurable Limits

Future INI settings for security limits:

```ini
; php.ini
sift.max_input_size = 67108864   ; 64 MB
sift.max_depth = 512
sift.max_pointer_segments = 256
```
