#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
port="${MCP_INSPECTOR_TEST_PORT:-18765}"
test_token="${MCP_INSPECTOR_TEST_TOKEN:-inspector-test-token}"
log_file="$(mktemp)"

cleanup() {
    if [[ -n "${server_pid:-}" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -f -- "$log_file"
}
trap cleanup EXIT

MCP_INSPECTOR_TEST_TOKEN="$test_token" php -S "127.0.0.1:$port" "$root_dir/scripts/inspector-fixture-router.php" >"$log_file" 2>&1 &
server_pid=$!

ready=false
for _attempt in {1..40}; do
    if curl --silent --fail --output /dev/null "http://127.0.0.1:$port/health"; then
        ready=true
        break
    fi
    sleep 0.25
done
if [[ "$ready" != true ]]; then
    sed -n '1,120p' "$log_file" >&2
    exit 1
fi

set +e
inspector_output="$(npx --yes @modelcontextprotocol/inspector@2.4.0 --cli \
    "http://127.0.0.1:$port/mcp" \
    --transport http \
    --method tools/list \
    --metadata "io.modelcontextprotocol/protocolVersion=2026-07-28" \
    --metadata 'io.modelcontextprotocol/clientCapabilities={}' \
    --header "Authorization: Bearer $test_token" 2>&1)"
inspector_status=$?
set -e

if [[ $inspector_status -eq 0 ]]; then
    printf '%s\n' "$inspector_output"
    printf 'MCP Inspector 2.4.0 completed the modern stateless tools/list probe.\n'
elif [[ "$inspector_output" == *'params._meta'* ]]; then
    printf 'MCP Inspector 2.4.0 sent its legacy initialization flow; the modern-only endpoint rejected the missing params._meta as expected.\n'
else
    printf '%s\n' "$inspector_output" >&2
    exit "$inspector_status"
fi
