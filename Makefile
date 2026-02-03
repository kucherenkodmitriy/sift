.PHONY: build install test bench clean docker-build docker-test

# Build the extension (debug mode)
build:
	cargo build

# Build the extension (release mode with optimizations)
release:
	cargo build --release

# Install the extension to PHP
install:
	cargo php install --release

# Run PHP tests (requires extension to be installed)
test-php:
	@echo "=== Running Sonic Tests ==="
	php tests/php/SonicTest.php
	@echo ""
	@echo "=== Running Sift & Query API Tests ==="
	php tests/php/SiftTest.php

# Run all tests (PHP tests only - Rust tests require PHP linking)
test: install test-php

# Build Docker development image
docker-build:
	docker build -f .docker/Dockerfile -t sonic-php:dev .

# Run tests in Docker
docker-test: docker-build
	docker run --rm sonic-php:dev sh -c "php tests/php/SonicTest.php && php tests/php/SiftTest.php"

# Run interactive Docker shell
docker-shell: docker-build
	docker run --rm -it -v $(PWD):/app sonic-php:dev bash

# Run benchmarks
bench: install
	@echo "=== Cold Start Benchmark ==="
	@echo "Testing json_decode vs Sonic::decode..."
	php benchmarks/cold_start.php
	@echo ""
	@echo "=== Lazy Extraction Benchmark ==="
	php benchmarks/lazy_get.php
	@echo ""
	@echo "=== Memory Usage Benchmark ==="
	php benchmarks/memory.php

# Clean build artifacts
clean:
	cargo clean
	rm -rf target/

# Format code
fmt:
	cargo fmt

# Lint code
lint:
	cargo clippy -- -D warnings

# Generate documentation
docs:
	cargo doc --no-deps --open
