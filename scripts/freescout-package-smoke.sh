#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    printf 'Usage: scripts/freescout-package-smoke.sh /path/to/McpServer.zip /path/to/freescout\n' >&2
    exit 2
fi

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
archive="$(realpath "$1")"
freescout_root="$(realpath "$2")"
stage_dir="$(mktemp -d)"

cleanup() {
    rm -rf -- "$stage_dir"
}
trap cleanup EXIT

"$root_dir/scripts/validate-release.sh" "$archive"
unzip -q "$archive" -d "$stage_dir"
php "$root_dir/scripts/freescout-runtime-smoke.php" "$freescout_root" "$stage_dir/McpServer"
printf 'Packaged module passed in the FreeScout dependency runtime.\n'

