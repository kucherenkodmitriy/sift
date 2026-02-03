//! Sonic-rs logic wrappers for JSON parsing operations.

use crate::errors::SonicError;
use ext_php_rs::convert::IntoZval;
use ext_php_rs::types::Zval;
use sonic_rs::{JsonContainerTrait, JsonValueTrait, LazyValue, PointerNode, Value};
use sonic_rs::{to_array_iter_unchecked, to_object_iter_unchecked};
use faststr::FastStr;

/// Maximum allowed nesting depth to prevent stack overflow.
/// PHP's default json_decode limit is 512.
const MAX_DEPTH: usize = 512;

/// Maximum allowed JSON input size (64 MB).
const MAX_INPUT_SIZE: usize = 64 * 1024 * 1024;

/// Maximum allowed pointer segments to prevent DoS.
const MAX_POINTER_SEGMENTS: usize = 256;

/// Converts a sonic_rs LazyValue to a PHP Zval with depth tracking.
/// LazyValue wraps unparsed JSON - primitives are extracted directly,
/// arrays/objects use lazy iteration to avoid full parsing upfront.
fn lazyvalue_to_zval(lazy: LazyValue) -> Result<Zval, SonicError> {
    lazyvalue_to_zval_with_depth(lazy, 0)
}

/// Internal: converts LazyValue to Zval with depth tracking to prevent stack overflow.
fn lazyvalue_to_zval_with_depth(lazy: LazyValue, depth: usize) -> Result<Zval, SonicError> {
    if depth > MAX_DEPTH {
        return Err(SonicError::ParseError(format!(
            "Maximum nesting depth ({}) exceeded",
            MAX_DEPTH
        )));
    }

    let mut zval = Zval::new();

    if lazy.is_null() {
        zval.set_null();
    } else if lazy.is_boolean() {
        let b = lazy.as_bool().unwrap();
        b.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if lazy.is_i64() {
        let n = lazy.as_i64().unwrap();
        n.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if lazy.is_u64() {
        let n = lazy.as_u64().unwrap();
        // Check if value fits in i64 to prevent silent overflow
        if n <= i64::MAX as u64 {
            (n as i64)
                .set_zval(&mut zval, false)
                .map_err(|e| SonicError::TypeError(e.to_string()))?;
        } else {
            // Value too large for i64, convert to float to preserve precision
            (n as f64)
                .set_zval(&mut zval, false)
                .map_err(|e| SonicError::TypeError(e.to_string()))?;
        }
    } else if lazy.is_f64() {
        let n = lazy.as_f64().unwrap();
        n.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if lazy.is_str() {
        let s = lazy.as_str().unwrap();
        s.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if lazy.is_array() {
        // Use lazy iteration - parses elements on-demand
        let mut php_arr = ext_php_rs::types::ZendHashTable::new();
        // SAFETY: we've verified this is an array via is_array()
        for item in unsafe { to_array_iter_unchecked(lazy.as_raw_str()) } {
            let item = item.map_err(|e| SonicError::ParseError(e.to_string()))?;
            let item_zval = lazyvalue_to_zval_with_depth(item, depth + 1)?;
            php_arr.push(item_zval).map_err(|e| {
                SonicError::TypeError(format!("Failed to push array item: {}", e))
            })?;
        }
        php_arr
            .set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if lazy.is_object() {
        // Use lazy iteration - parses key/value pairs on-demand
        let mut php_arr = ext_php_rs::types::ZendHashTable::new();
        // SAFETY: we've verified this is an object via is_object()
        for entry in unsafe { to_object_iter_unchecked(lazy.as_raw_str()) } {
            let (key, val) = entry.map_err(|e| SonicError::ParseError(e.to_string()))?;
            let val_zval = lazyvalue_to_zval_with_depth(val, depth + 1)?;
            php_arr.insert(&*key, val_zval).map_err(|e| {
                SonicError::TypeError(format!("Failed to insert object key: {}", e))
            })?;
        }
        php_arr
            .set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else {
        return Err(SonicError::TypeError("Unknown JSON value type".to_string()));
    }

    Ok(zval)
}

/// Converts a sonic_rs Value to a PHP Zval with depth tracking.
pub fn value_to_zval(value: &Value) -> Result<Zval, SonicError> {
    value_to_zval_with_depth(value, 0)
}

/// Internal: converts Value to Zval with depth tracking to prevent stack overflow.
fn value_to_zval_with_depth(value: &Value, depth: usize) -> Result<Zval, SonicError> {
    if depth > MAX_DEPTH {
        return Err(SonicError::ParseError(format!(
            "Maximum nesting depth ({}) exceeded",
            MAX_DEPTH
        )));
    }

    let mut zval = Zval::new();

    if value.is_null() {
        zval.set_null();
    } else if value.is_boolean() {
        let b = value.as_bool().unwrap();
        b.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if value.is_i64() {
        let n = value.as_i64().unwrap();
        n.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if value.is_u64() {
        let n = value.as_u64().unwrap();
        // Check if value fits in i64 to prevent silent overflow
        if n <= i64::MAX as u64 {
            (n as i64)
                .set_zval(&mut zval, false)
                .map_err(|e| SonicError::TypeError(e.to_string()))?;
        } else {
            // Value too large for i64, convert to float to preserve precision
            (n as f64)
                .set_zval(&mut zval, false)
                .map_err(|e| SonicError::TypeError(e.to_string()))?;
        }
    } else if value.is_f64() {
        let n = value.as_f64().unwrap();
        n.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if value.is_str() {
        let s = value.as_str().unwrap();
        s.set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if value.is_array() {
        let arr = value.as_array().unwrap();
        let mut php_arr = ext_php_rs::types::ZendHashTable::new();
        for item in arr.iter() {
            let item_zval = value_to_zval_with_depth(item, depth + 1)?;
            php_arr.push(item_zval).map_err(|e| {
                SonicError::TypeError(format!("Failed to push array item: {}", e))
            })?;
        }
        php_arr
            .set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else if value.is_object() {
        let obj = value.as_object().unwrap();
        let mut php_arr = ext_php_rs::types::ZendHashTable::new();
        for (key, val) in obj.iter() {
            let val_zval = value_to_zval_with_depth(val, depth + 1)?;
            php_arr.insert(key, val_zval).map_err(|e| {
                SonicError::TypeError(format!("Failed to insert object key: {}", e))
            })?;
        }
        php_arr
            .set_zval(&mut zval, false)
            .map_err(|e| SonicError::TypeError(e.to_string()))?;
    } else {
        return Err(SonicError::TypeError("Unknown JSON value type".to_string()));
    }

    Ok(zval)
}

/// Full JSON decode - parses entire JSON string into PHP value.
pub fn decode(json: &str) -> Result<Zval, SonicError> {
    // Validate input size to prevent DoS
    if json.len() > MAX_INPUT_SIZE {
        return Err(SonicError::ParseError(format!(
            "Input size ({} bytes) exceeds maximum allowed ({} bytes)",
            json.len(),
            MAX_INPUT_SIZE
        )));
    }

    let value: Value = sonic_rs::from_str(json)?;
    value_to_zval(&value)
}

/// Lazy get - extracts a value by JSON pointer WITHOUT full decode.
/// Uses sonic_rs::get() which uses SIMD to skip irrelevant content.
/// Pointer format: "/users/0/email" (RFC 6901)
pub fn get_by_pointer(json: &str, pointer: &str) -> Result<Zval, SonicError> {
    // Validate input size to prevent DoS
    if json.len() > MAX_INPUT_SIZE {
        return Err(SonicError::ParseError(format!(
            "Input size ({} bytes) exceeds maximum allowed ({} bytes)",
            json.len(),
            MAX_INPUT_SIZE
        )));
    }

    // Validate pointer format
    if !pointer.is_empty() && !pointer.starts_with('/') {
        return Err(SonicError::InvalidPointer(
            "Pointer must start with '/' or be empty".to_string()
        ));
    }

    // Empty pointer means return the whole document
    if pointer.is_empty() {
        let value: Value = sonic_rs::from_str(json)?;
        return value_to_zval(&value);
    }

    // Parse RFC 6901 pointer into path segments with owned strings
    let segments: Vec<String> = pointer[1..]
        .split('/')
        .map(|part| part.replace("~1", "/").replace("~0", "~"))
        .collect();

    // Validate pointer segment count to prevent DoS
    if segments.len() > MAX_POINTER_SEGMENTS {
        return Err(SonicError::InvalidPointer(format!(
            "Pointer has too many segments ({}, max {})",
            segments.len(),
            MAX_POINTER_SEGMENTS
        )));
    }

    // Build pointer nodes - need to determine if each segment is an index or key
    // Use owned FastStr to avoid lifetime issues
    let nodes: Vec<PointerNode> = segments
        .into_iter()
        .map(|seg| {
            if let Ok(idx) = seg.parse::<usize>() {
                PointerNode::Index(idx)
            } else {
                PointerNode::Key(FastStr::new(seg))
            }
        })
        .collect();

    // Use sonic_rs::get for true lazy extraction (SIMD-accelerated skip)
    let lazy_value = sonic_rs::get(json, nodes.as_slice()).map_err(|_| {
        SonicError::KeyNotFound("Path not found".to_string())
    })?;

    lazyvalue_to_zval(lazy_value)
}

/// Validate JSON syntax.
/// Note: This currently does a full parse. For very large inputs,
/// consider checking size first in the calling code.
pub fn is_valid(json: &str) -> bool {
    // Reject oversized inputs to prevent DoS
    if json.len() > MAX_INPUT_SIZE {
        return false;
    }
    // TODO: sonic-rs doesn't have a dedicated validation-only function,
    // so we have to do a full parse. Consider using a streaming validator
    // for better performance on large inputs.
    sonic_rs::from_str::<Value>(json).is_ok()
}

// Note: Rust unit tests are limited because ext-php-rs types (Zval) require
// PHP to be linked. The comprehensive test suite is in tests/php/SonicTest.php
// and tests/php/SiftTest.php which test all functionality through the PHP extension.
