# sonic-php development environment
FROM php:8.3-cli-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    build-essential \
    clang \
    libclang-dev \
    pkg-config \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# Install cargo-php for building PHP extensions
RUN cargo install cargo-php

WORKDIR /app

# Copy source files
COPY Cargo.toml Cargo.lock* ./
COPY src ./src

# Build the extension
RUN cargo build --release

# Create php.ini and manually install the extension
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini && \
    cp target/release/libsonic_php.so $(php -r "echo ini_get('extension_dir');")/sonic_php.so && \
    echo "extension=sonic_php.so" >> /usr/local/etc/php/php.ini

# Copy test files
COPY tests ./tests

# Default command runs PHP tests
CMD ["php", "-m"]
