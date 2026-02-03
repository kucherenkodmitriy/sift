# Contributing to Sift

Thank you for your interest in contributing to Sift! This document provides guidelines for contributing to the project.

## Getting Started

### Prerequisites

- Rust 1.70+ (stable)
- PHP 8.1+ with development headers
- Docker (recommended for development)

### Development Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/sift.git
cd sift

# Build and test using Docker (recommended)
make docker-test

# Or build locally
cargo build
cargo php install --release
make test-php
```

## How to Contribute

### Reporting Bugs

If you find a bug, please open an issue with:
- A clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- PHP version and OS information
- Minimal code example demonstrating the issue

### Suggesting Features

Feature requests are welcome! Please open an issue describing:
- The use case for the feature
- How it would work from a user perspective
- Any implementation ideas you have

### Pull Requests

1. **Fork the repository** and create a new branch from `main`
2. **Make your changes** following the code style guidelines
3. **Add tests** for any new functionality
4. **Run the test suite** to ensure everything passes
5. **Update documentation** if needed
6. **Submit a pull request** with a clear description of the changes

## Development Guidelines

### Code Style

**Rust:**
- Follow standard Rust formatting: `make fmt`
- Run clippy and fix warnings: `make lint`
- Add comments for complex logic
- Use meaningful variable names

**PHP Tests:**
- Follow existing test patterns in `tests/php/`
- Use descriptive test names
- Include both positive and negative test cases

### Testing

All changes must include tests:

```bash
# Run all tests
make docker-test

# Run PHP tests only (requires extension installed)
make test-php

# Run benchmarks
make bench
```

### Security

Security is a top priority. When contributing:
- Never log user-provided data
- Validate all inputs
- Follow existing security patterns (size limits, depth limits, etc.)
- Report security issues privately to the maintainers

### Documentation

Update documentation for any user-facing changes:
- `README.md` - API changes, new features
- `docs/ARCHITECTURE.md` - Technical implementation details
- `docs/CHANGELOG.md` - All changes in the Unreleased section

## Project Structure

```
sift/
├── src/
│   ├── lib.rs          # PHP module registration
│   ├── parser.rs       # Core parsing logic
│   ├── query.rs        # Query API implementation
│   └── errors.rs       # Error types
├── tests/
│   └── php/            # PHP integration tests
├── benchmarks/         # Performance benchmarks
└── docs/               # Documentation
```

## Commit Messages

Write clear, concise commit messages:

```
Add support for streaming JSON parsing

- Implement StreamParser for large files
- Add tests for stream operations
- Update documentation with streaming examples
```

## Code Review Process

1. All PRs require at least one review
2. CI tests must pass
3. Code coverage should not decrease
4. Documentation must be updated

## Questions?

Feel free to open an issue for any questions about contributing!

## License

By contributing to Sift, you agree that your contributions will be licensed under the MIT License.
