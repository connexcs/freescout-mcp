<?php

$root = dirname(__DIR__);
$codex = file_get_contents($root.'/tests/Interop/codex-config.toml');
$claude = json_decode(file_get_contents($root.'/tests/Interop/claude.mcp.json'), true, 512, JSON_THROW_ON_ERROR);

if (1 !== preg_match('/\[mcp_servers\.freescout\][\s\S]*url\s*=\s*"https:\/\/support\.example\.test\/mcp"[\s\S]*bearer_token_env_var\s*=\s*"FREESCOUT_MCP_TOKEN"/', $codex)) {
    throw new RuntimeException('Codex config does not use the documented remote HTTP bearer-token environment setting.');
}

$server = $claude['mcpServers']['freescout'] ?? [];
if ('http' !== ($server['type'] ?? null)
    || 'https://support.example.test/mcp' !== ($server['url'] ?? null)
    || 'Bearer ${FREESCOUT_MCP_TOKEN}' !== ($server['headers']['Authorization'] ?? null)
) {
    throw new RuntimeException('Claude Code config does not use the documented remote HTTP Authorization header setting.');
}

foreach ([$codex, json_encode($claude, JSON_THROW_ON_ERROR)] as $config) {
    if (1 === preg_match('/fsmcp_[A-Za-z0-9_-]{16,}/', $config)) {
        throw new RuntimeException('A client fixture contains a literal MCP bearer token.');
    }
}

fwrite(STDOUT, "Codex config.toml and Claude Code .mcp.json bearer environment configurations passed.\n");

