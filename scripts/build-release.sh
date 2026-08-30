#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="$(tr -d '[:space:]' < "$root_dir/version.txt")"
build_dir="$root_dir/build"
archive="$build_dir/McpServer-$version.zip"
stage_dir="$(mktemp -d)"

cleanup() {
    rm -rf -- "$stage_dir"
}
trap cleanup EXIT

mkdir -p "$stage_dir/McpServer" "$build_dir"

tar \
    --exclude='.git' \
    --exclude='vendor' \
    --exclude='build' \
    --exclude='.phpunit.cache' \
    --exclude='.github' \
    --exclude='tests' \
    --exclude='scripts' \
    --exclude='phpunit.xml.dist' \
    -C "$root_dir" \
    -cf - . \
    | tar -x -C "$stage_dir/McpServer"
COMPOSER_ROOT_VERSION="$version" composer install \
    --working-dir="$stage_dir/McpServer" \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

rm -f -- "$archive"
(
    cd "$stage_dir"
    zip -qr "$archive" McpServer
)

printf 'Built %s\n' "$archive"
