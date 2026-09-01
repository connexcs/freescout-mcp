#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="$(tr -d '[:space:]' < "$root_dir/version.txt")"
archive="${1:-$root_dir/build/McpServer-$version.zip}"
stage_dir="$(mktemp -d)"

cleanup() {
    rm -rf -- "$stage_dir"
}
trap cleanup EXIT

if [[ ! -f "$archive" ]]; then
    printf 'Release archive not found: %s\n' "$archive" >&2
    exit 1
fi

archive_list="$(zipinfo -1 "$archive")"
for required in \
    McpServer/module.json \
    McpServer/version.txt \
    McpServer/LICENSE \
    McpServer/CHANGELOG.md \
    McpServer/Public/ \
    McpServer/vendor/autoload.php; do
    if ! grep -Fxq "$required" <<<"$archive_list"; then
        printf 'Release archive is missing %s\n' "$required" >&2
        exit 1
    fi
done

if grep -Ev '^McpServer/' <<<"$archive_list" | grep -q . \
    || grep -Eq '^McpServer/(\.git($|/)|\.gitignore$|\.env($|\.)|auth\.json$|build($|/)|scripts($|/)|tests($|/)|\.github($|/)|phpunit\.xml)' <<<"$archive_list"; then
    printf 'Release archive contains a development or incorrectly rooted path.\n' >&2
    exit 1
fi
if grep -Eq '^McpServer/vendor/(phpunit|sebastian|mockery)/' <<<"$archive_list"; then
    printf 'Release archive contains development dependencies.\n' >&2
    exit 1
fi

unzip -q "$archive" -d "$stage_dir"
ARCHIVE_MODULE="$stage_dir/McpServer" EXPECTED_VERSION="$version" php -r '
    $root = getenv("ARCHIVE_MODULE");
    $expected = getenv("EXPECTED_VERSION");
    require $root."/vendor/autoload.php";
    $manifest = json_decode(file_get_contents($root."/module.json"), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest["version"] ?? null) !== $expected || trim(file_get_contents($root."/version.txt")) !== $expected) {
        throw new RuntimeException("Packaged versions do not match.");
    }
    if (!class_exists(\Modules\McpServer\Services\McpServerFactory::class)) {
        throw new RuntimeException("Packaged production autoloader cannot load the module.");
    }
'

printf 'Validated production release archive %s\n' "$archive"
