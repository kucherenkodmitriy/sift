//! Lazy Query object that stays in Rust domain until hydration.
//!
//! Usage:
//! ```php
//! $q = Sift::query($json);
//! $email = $q->pointer("/users/5000/email")->string();
//! // Or chainable (path accumulated, single extraction):
//! $email = $q->get("users")->index(5)->get("email")->string();
//! ```

use crate::errors::SonicError;
use crate::parser;
use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use faststr::FastStr;
use sonic_rs::{JsonValueTrait, PointerNode};
use std::sync::Arc;

/// Maximum allowed JSON input size (64 MB).
const MAX_INPUT_SIZE: usize = 64 * 1024 * 1024;

/// Maximum allowed path segments to prevent DoS.
const MAX_PATH_SEGMENTS: usize = 256;

/// A path segment for lazy path building.
/// Uses FastStr for zero-copy key storage where possible.
#[derive(Clone, Debug)]
enum PathSegment {
    Key(FastStr),
    Index(usize),
}

/// Query - a lazy JSON cursor that stays in Rust until hydration.
/// Path segments are accumulated and only resolved on hydration.
/// Uses Arc for zero-copy JSON sharing across navigations.
#[php_class(name = "Sift\\Query")]
#[derive(Clone, Debug)]
pub struct Query {
    /// The original JSON string (shared via Arc for zero-copy)
    json: Arc<String>,
    /// Accumulated path segments (lazy - not resolved until hydration)
    path: Vec<PathSegment>,
}

impl Query {
    /// Create a new Query from a JSON string.
    /// Note: Input size is validated on hydration, not creation,
    /// to allow Query objects to be created without immediate validation.
    pub fn new(json: String) -> Self {
        Self {
            json: Arc::new(json),
            path: Vec::new(),
        }
    }

    /// Validate input size before processing.
    fn validate_input_size(&self) -> Result<(), SonicError> {
        if self.json.len() > MAX_INPUT_SIZE {
            return Err(SonicError::ParseError(format!(
                "Input size ({} bytes) exceeds maximum allowed ({} bytes)",
                self.json.len(),
                MAX_INPUT_SIZE
            )));
        }
        Ok(())
    }

    /// Internal: resolve the accumulated path
    fn resolve(&self) -> Result<sonic_rs::LazyValue<'_>, SonicError> {
        // Validate input size on resolution
        self.validate_input_size()?;

        if self.path.is_empty() {
            return sonic_rs::get(self.json.as_str(), &[] as &[PointerNode])
                .map_err(|e| SonicError::ParseError(e.to_string()));
        }

        // Build pointer nodes from accumulated path - FastStr clone is cheap (Arc-based)
        let nodes: Vec<PointerNode> = self
            .path
            .iter()
            .map(|seg| match seg {
                PathSegment::Key(k) => PointerNode::Key(k.clone()),
                PathSegment::Index(i) => PointerNode::Index(*i),
            })
            .collect();

        sonic_rs::get(self.json.as_str(), nodes.as_slice())
            .map_err(|_| SonicError::KeyNotFound("Path not found".to_string()))
    }
}

#[php_impl]
impl Query {
    /// Navigate to a path using JSON pointer (RFC 6901).
    /// The path is accumulated, not immediately resolved.
    ///
    /// # Example
    /// ```php
    /// $q = Sift::query($json)->pointer("/users/0/email");
    /// ```
    pub fn pointer(&self, ptr: &str) -> Result<Query, SonicError> {
        if ptr.is_empty() {
            return Ok(self.clone());
        }

        if !ptr.starts_with('/') {
            return Err(SonicError::InvalidPointer(
                "Pointer must start with '/' or be empty".to_string()
            ));
        }

        // Parse and accumulate segments
        let mut new_path = self.path.clone();
        for part in ptr[1..].split('/') {
            // Check path segment limit
            if new_path.len() >= MAX_PATH_SEGMENTS {
                return Err(SonicError::InvalidPointer(format!(
                    "Path has too many segments (max {})",
                    MAX_PATH_SEGMENTS
                )));
            }

            let unescaped = part.replace("~1", "/").replace("~0", "~");
            if let Ok(idx) = unescaped.parse::<usize>() {
                new_path.push(PathSegment::Index(idx));
            } else {
                new_path.push(PathSegment::Key(FastStr::new(unescaped)));
            }
        }

        Ok(Query {
            json: Arc::clone(&self.json),
            path: new_path,
        })
    }

    /// Navigate into an object key. Path is accumulated, not resolved yet.
    /// Returns an error if path segment limit is exceeded.
    ///
    /// # Example
    /// ```php
    /// $q = Sift::query($json)->get("users")->get("email");
    /// ```
    pub fn get(&self, key: &str) -> Result<Query, SonicError> {
        // Check path segment limit
        if self.path.len() >= MAX_PATH_SEGMENTS {
            return Err(SonicError::InvalidPointer(format!(
                "Path has too many segments (max {})",
                MAX_PATH_SEGMENTS
            )));
        }

        let mut new_path = self.path.clone();
        new_path.push(PathSegment::Key(FastStr::new(key)));
        Ok(Query {
            json: Arc::clone(&self.json),
            path: new_path,
        })
    }

    /// Navigate into an array by index. Path is accumulated, not resolved yet.
    /// Returns an error if index is negative or path segment limit is exceeded.
    ///
    /// # Example
    /// ```php
    /// $q = Sift::query($json)->get("users")->index(5)->get("email");
    /// ```
    pub fn index(&self, idx: i64) -> Result<Query, SonicError> {
        // Validate non-negative index
        if idx < 0 {
            return Err(SonicError::InvalidPointer(format!(
                "Array index must be non-negative, got {}",
                idx
            )));
        }

        // Check path segment limit
        if self.path.len() >= MAX_PATH_SEGMENTS {
            return Err(SonicError::InvalidPointer(format!(
                "Path has too many segments (max {})",
                MAX_PATH_SEGMENTS
            )));
        }

        let mut new_path = self.path.clone();
        new_path.push(PathSegment::Index(idx as usize));
        Ok(Query {
            json: Arc::clone(&self.json),
            path: new_path,
        })
    }

    // === Hydration methods - these resolve the path and create PHP values ===

    /// Extract as PHP string. Only now is the path resolved.
    pub fn string(&self) -> Result<String, SonicError> {
        let lazy = self.resolve()?;
        lazy.as_str()
            .map(|s| s.to_string())
            .ok_or_else(|| SonicError::TypeError("Value is not a string".to_string()))
    }

    /// Extract as PHP integer.
    pub fn int(&self) -> Result<i64, SonicError> {
        let lazy = self.resolve()?;
        lazy.as_i64()
            .ok_or_else(|| SonicError::TypeError("Value is not an integer".to_string()))
    }

    /// Extract as PHP float.
    pub fn float(&self) -> Result<f64, SonicError> {
        let lazy = self.resolve()?;
        lazy.as_f64()
            .ok_or_else(|| SonicError::TypeError("Value is not a float".to_string()))
    }

    /// Extract as PHP boolean.
    pub fn bool(&self) -> Result<bool, SonicError> {
        let lazy = self.resolve()?;
        lazy.as_bool()
            .ok_or_else(|| SonicError::TypeError("Value is not a boolean".to_string()))
    }

    /// Check if the value is null.
    pub fn is_null(&self) -> Result<bool, SonicError> {
        let lazy = self.resolve()?;
        Ok(lazy.is_null())
    }

    /// Get the raw JSON string at this path without parsing.
    /// Useful for passing JSON subsets to other systems.
    pub fn raw(&self) -> Result<String, SonicError> {
        let lazy = self.resolve()?;
        Ok(lazy.as_raw_str().to_string())
    }

    /// Full hydration to PHP array/value. Use sparingly.
    pub fn value(&self) -> Result<Zval, SonicError> {
        let lazy = self.resolve()?;
        parser::decode(lazy.as_raw_str())
    }

    /// Check if this points to an array.
    pub fn is_array(&self) -> Result<bool, SonicError> {
        let lazy = self.resolve()?;
        Ok(lazy.is_array())
    }

    /// Check if this points to an object.
    pub fn is_object(&self) -> Result<bool, SonicError> {
        let lazy = self.resolve()?;
        Ok(lazy.is_object())
    }

    /// Get the type of the current value as a string.
    pub fn get_type(&self) -> Result<String, SonicError> {
        let lazy = self.resolve()?;
        let t = if lazy.is_null() {
            "null"
        } else if lazy.is_boolean() {
            "boolean"
        } else if lazy.is_i64() || lazy.is_u64() {
            "integer"
        } else if lazy.is_f64() {
            "float"
        } else if lazy.is_str() {
            "string"
        } else if lazy.is_array() {
            "array"
        } else if lazy.is_object() {
            "object"
        } else {
            "unknown"
        };
        Ok(t.to_string())
    }
}

// Note: Rust unit tests are limited because ext-php-rs types (Zval) require
// PHP to be linked. The comprehensive test suite is in tests/php/SiftTest.php
// which tests all Query API functionality through the PHP extension.