<?php

$appHost = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

return [
    'enabled' => filter_var(env('MCP_SERVER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'mutations_enabled' => filter_var(env('MCP_SERVER_MUTATIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'server_name' => 'freescout-mcp',
    'server_title' => 'FreeScout MCP Server',
    'server_version' => '0.4.0',
    'allowed_hosts' => array_values(array_unique(array_filter(array_map(
        'trim',
        explode(',', $appHost.','.env('MCP_SERVER_ALLOWED_HOSTS', ''))
    )))),
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('MCP_SERVER_ALLOWED_ORIGINS', ''))
    ))),
    'max_body_bytes' => (int) env('MCP_SERVER_MAX_BODY_BYTES', 1024 * 1024),
    'catalog_ttl_ms' => (int) env('MCP_SERVER_CATALOG_TTL_MS', 300000),
    'token_pepper' => env('MCP_SERVER_TOKEN_PEPPER', env('APP_KEY', '')),
    'authenticated_rate_limit' => (int) env('MCP_SERVER_RATE_LIMIT', 120),
    'unauthenticated_rate_limit' => (int) env('MCP_SERVER_AUTH_RATE_LIMIT', 30),
    'options' => [
        'personal_tokens_enabled' => ['default' => true],
        'allow_non_admin_tokens' => ['default' => true],
        'token_lifetime_days' => ['default' => 90],
        'mutations_enabled' => ['default' => false],
    ],
];
