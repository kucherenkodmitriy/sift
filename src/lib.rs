//! Sift: SIMD-accelerated JSON parsing for PHP
//!
//! This extension provides high-performance JSON operations using the sonic-rs engine.

mod errors;
mod parser;
mod query;

use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use query::Query;

/// Sift class - main entry point for lazy JSON operations.
/// Stays in Rust domain until explicit hydration.
#[php_class(name = "Sift")]
pub struct Sift;

#[php_impl]
impl Sift {
    /// Create a lazy Query object for navigating JSON without creating PHP values.
    ///
    /// # Example
    /// ```php
    /// $q = Sift::query($json);
    /// $email = $q->pointer("/users/5000/email")->string();
    /// // Or chainable:
    /// $email = $q->get("users")->index(5000)->get("email")->string();
    /// ```
    #[php_static]
    pub fn query(json: String) -> Query {
        Query::new(json)
    }

    /// Quick extraction by pointer - convenience method.
    /// For single extractions, this is simpler than creating a Query.
    #[php_static]
    pub fn get(json: &str, pointer: &str) -> Result<Zval, errors::SonicError> {
        parser::get_by_pointer(json, pointer)
    }

    /// Full JSON decode.
    #[php_static]
    pub fn decode(json: &str) -> Result<Zval, errors::SonicError> {
        parser::decode(json)
    }

    /// SIMD-accelerated JSON validation.
    #[php_static]
    pub fn is_valid(json: &str) -> bool {
        parser::is_valid(json)
    }
}

/// Sonic class - legacy alias, kept for backwards compatibility.
#[php_class(name = "Sonic")]
pub struct Sonic;

#[php_impl]
impl Sonic {
    /// Extract a value by JSON pointer without full decode.
    ///
    /// # Arguments
    /// * `json` - The JSON string to parse
    /// * `pointer` - JSON pointer path (e.g., "/users/0/email")
    ///
    /// # Returns
    /// The value at the specified path, or throws SonicException if not found.
    ///
    /// # Example
    /// ```php
    /// $email = Sonic::get($json, "/users/0/email");
    /// ```
    #[php_static]
    pub fn get(json: &str, pointer: &str) -> Result<Zval, errors::SonicError> {
        // Note: Don't log pointer - it may contain sensitive field names
        log::debug!("Sonic::get called");
        parser::get_by_pointer(json, pointer)
    }

    /// Full JSON decode - high-speed replacement for json_decode.
    ///
    /// # Arguments
    /// * `json` - The JSON string to parse
    ///
    /// # Returns
    /// PHP array/object representation of the JSON.
    ///
    /// # Example
    /// ```php
    /// $data = Sonic::decode($jsonString);
    /// ```
    #[php_static]
    pub fn decode(json: &str) -> Result<Zval, errors::SonicError> {
        log::debug!("Sonic::decode called");
        parser::decode(json)
    }

    /// SIMD-accelerated JSON validation.
    ///
    /// # Arguments
    /// * `json` - The JSON string to validate
    ///
    /// # Returns
    /// true if valid JSON, false otherwise.
    ///
    /// # Example
    /// ```php
    /// if (Sonic::isValid($input)) {
    ///     // Process valid JSON
    /// }
    /// ```
    #[php_static]
    pub fn is_valid(json: &str) -> bool {
        log::debug!("Sonic::isValid called");
        parser::is_valid(json)
    }
}

/// Initialize logging bridge on module startup.
fn init_logger() {
    // Initialize env_logger - respects RUST_LOG environment variable
    // In production, this would bridge to PHP's error logging
    let _ = env_logger::builder()
        .filter_level(log::LevelFilter::Warn)
        .is_test(false)
        .try_init();
}

/// PHP module registration.
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    init_logger();
    log::info!("sonic-php extension loaded");
    module
}

// Note: Rust unit tests require PHP to be linked (ext-php-rs dependency).
// The comprehensive test suite is in tests/php/SonicTest.php and
// tests/php/SiftTest.php which test all functionality through the PHP extension.
// Run tests with: make test-php (or make docker-test for containerized testing)