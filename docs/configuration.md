# Server configuration

All environment settings belong in FreeScout's `.env`. Run `php artisan freescout:clear-cache` after changing them.

## Recommended production baseline

```dotenv
APP_URL=https://support.example.com
MCP_SERVER_ENABLED=true
MCP_SERVER_ALLOWED_HOSTS=support.example.com
MCP_SERVER_ALLOWED_ORIGINS=
MCP_SERVER_MAX_BODY_BYTES=1048576
MCP_SERVER_MAX_TOOL_OUTPUT_BYTES=1048576
MCP_SERVER_CATALOG_TTL_MS=300000
MCP_SERVER_RATE_LIMIT=120
MCP_SERVER_AUTH_RATE_LIMIT=30
MCP_SERVER_MUTATIONS_ENABLED=false
MCP_SERVER_OAUTH_ENABLED=true
MCP_SERVER_OAUTH_ISSUER=https://support.example.com
MCP_SERVER_OAUTH_DCR_ENABLED=true
MCP_SERVER_OAUTH_ACCESS_TOKEN_LIFETIME=3600
MCP_SERVER_OAUTH_REFRESH_TOKEN_LIFETIME=2592000
MCP_SERVER_OAUTH_CIMD_CACHE=3600
```

## Reference

| Setting | Default | Purpose |
| --- | --- | --- |
| `MCP_SERVER_ENABLED` | `false` | Master switch for `/mcp` and OAuth discovery. Enable last. |
| `MCP_SERVER_ALLOWED_HOSTS` | Host from `APP_URL` | Additional comma-separated request hosts. Do not use URLs or paths. |
| `MCP_SERVER_ALLOWED_ORIGINS` | empty | Exact comma-separated browser origins. CLI clients normally send no Origin and need no entry. |
| `MCP_SERVER_MAX_BODY_BYTES` | `1048576` | Maximum MCP POST body size. |
| `MCP_SERVER_MAX_TOOL_OUTPUT_BYTES` | `1048576` | Maximum JSON-encoded tool result size. |
| `MCP_SERVER_CATALOG_TTL_MS` | `300000` | Private per-user catalogue cache hint. |
| `MCP_SERVER_RATE_LIMIT` | `120` | Authenticated requests per token per minute. |
| `MCP_SERVER_AUTH_RATE_LIMIT` | `30` | Failed authentication attempts per source IP per minute. |
| `MCP_SERVER_MUTATIONS_ENABLED` | `false` | Environment gate for all write tools; the administrator UI gate must also be enabled. |
| `MCP_SERVER_TOKEN_PEPPER` | `APP_KEY` | Secret HMAC key for token verifiers and fingerprints. Changing it invalidates credentials. |
| `MCP_SERVER_OAUTH_ENABLED` | `true` | Environment gate for OAuth; the administrator UI gate also applies. |
| `MCP_SERVER_OAUTH_ISSUER` | `APP_URL` | Stable OAuth issuer. Use a public HTTPS URL outside local development. |
| `MCP_SERVER_OAUTH_DCR_ENABLED` | `true` | Deprecated Dynamic Client Registration fallback. Disable when every client uses CIMD or pre-registration. |
| `MCP_SERVER_OAUTH_ACCESS_TOKEN_LIFETIME` | `3600` | Access-token lifetime in seconds. |
| `MCP_SERVER_OAUTH_REFRESH_TOKEN_LIFETIME` | `2592000` | Refresh-token lifetime in seconds. |
| `MCP_SERVER_OAUTH_CIMD_CACHE` | `3600` | Client ID Metadata Document cache duration in seconds. |

## Administrator policies

**Manage → Settings → MCP Server** controls policies stored in FreeScout:

- **Personal MCP tokens** immediately accepts or rejects all personal tokens without deleting them.
- **Regular users** determines whether non-administrators may create and use personal tokens.
- **Token lifetime** applies to newly created personal tokens; `0` disables automatic expiry.
- **OAuth connections** controls browser-authorized clients separately from personal tokens.
- **Write tools** is the second mutation gate and has no effect unless `MCP_SERVER_MUTATIONS_ENABLED=true`.

The environment master switches let an operator stop traffic even if an administrator setting is enabled. FreeScout user, mailbox, and assigned-only permissions are always enforced and cannot be widened by these settings.

