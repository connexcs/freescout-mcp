#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

find "$root_dir" \
    -path "$root_dir/vendor" -prune -o \
    -path "$root_dir/build" -prune -o \
    -type f -name '*.php' -print0 \
    | sort -z \
    | xargs -0 -n1 php -l
