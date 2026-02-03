//! Custom PHP Exception mapping for sonic-php errors.

use ext_php_rs::exception::PhpException;
use ext_php_rs::zend::ce;
use thiserror::Error;

/// Errors that can occur during JSON operations.
#[derive(Error, Debug)]
pub enum SonicError {
    #[error("JSON parse error: {0}")]
    ParseError(String),

    #[error("Invalid JSON pointer: {0}")]
    InvalidPointer(String),

    #[error("Key not found: {0}")]
    KeyNotFound(String),

    #[error("Type conversion error: {0}")]
    TypeError(String),

    #[error("IO error: {0}")]
    IoError(String),
}

impl From<sonic_rs::Error> for SonicError {
    fn from(err: sonic_rs::Error) -> Self {
        SonicError::ParseError(err.to_string())
    }
}

impl From<std::io::Error> for SonicError {
    fn from(err: std::io::Error) -> Self {
        SonicError::IoError(err.to_string())
    }
}

impl From<SonicError> for PhpException {
    fn from(err: SonicError) -> Self {
        PhpException::new(err.to_string(), 0, ce::exception())
    }
}

// Note: Error handling is tested through PHP integration tests in
// tests/php/SonicTest.php and tests/php/SiftTest.php