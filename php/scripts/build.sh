#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/build"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Install prod deps and generate optimized autoloader into build/vendor
( cd "$ROOT_DIR" && rm -rf vendor && composer install --no-dev --prefer-dist --no-interaction --no-progress )

# Copy package files
cp -R "$ROOT_DIR/src" "$BUILD_DIR/src"
cp -R "$ROOT_DIR/vendor" "$BUILD_DIR/vendor"

# Copy metadata
cp "$ROOT_DIR/composer.json" "$BUILD_DIR/composer.json"
[ -f "$ROOT_DIR/README.md" ] && cp "$ROOT_DIR/README.md" "$BUILD_DIR/README.md"
[ -f "$ROOT_DIR/../LICENSE" ] && cp "$ROOT_DIR/../LICENSE" "$BUILD_DIR/LICENSE"

# Optimize autoload for dist
( cd "$BUILD_DIR" && composer dump-autoload -o )

echo "Build completed at: $BUILD_DIR"
