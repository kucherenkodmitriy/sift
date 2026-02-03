# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial project structure with Rust/PHP integration
- `Sonic::get()` - Lazy JSON pointer extraction without full decode
- `Sonic::decode()` - High-speed full JSON parsing
- `Sonic::isValid()` - SIMD-accelerated JSON validation
- `Sift::query()` - Lazy Query API with chainable navigation
- Docker development environment
- Benchmark suite for performance comparison
- PHP test suite

### Security
- **Input size validation**: 64 MB maximum to prevent memory exhaustion attacks
- **Nesting depth limits**: 512 levels maximum (matching PHP's json_decode) to prevent stack overflow
- **Pointer segment limits**: 256 segments maximum to prevent DoS via long paths
- **Integer overflow protection**: Large u64 values safely convert to float instead of overflowing
- **Negative index validation**: Array indices must be non-negative
- **Error message sanitization**: Prevents information leakage in error messages

### Technical
- Integration with sonic-rs for SIMD JSON parsing
- ext-php-rs bindings for PHP 8.x
- Custom error types mapped to PHP exceptions
- Zero-copy string handling where possible
- Arc-based JSON sharing in Query API for efficient memory usage
- Depth-limited recursion in value conversion functions

## [0.1.0] - TBD

### Added
- MVP release with core functionality
- Documentation and examples
