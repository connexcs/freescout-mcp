# Troubleshooting

## First checks

1. Confirm FreeScout, PHP, and required extensions meet [Installation](installation.md).
2. Confirm the module is active under **Manage → Modules**.
3. Check **Manage → System → Logs** and `storage/logs` without copying credentials or customer content into a support request.
4. Run `php artisan migrate --force` and `php artisan freescout:clear-cache` from the FreeScout root.
5. Confirm `APP_URL`, `MCP_SERVER_OAUTH_ISSUER`, proxy paths, and TLS all describe the same public deployment.
6. Run the authenticated discovery request in [Installation](installation.md).

## HTTP failures

| Result | Likely cause | Action |
| --- | --- | --- |
| `404` on OAuth metadata | Endpoint or OAuth disabled, wrong subdirectory, or proxy path missing | Enable both gates and proxy well-known paths. |
| `400` JSON-RPC metadata/header error | Client is using an older MCP lifecycle or a proxy removed/changed MCP headers | Use a `2026-07-28` client and forward all required headers unchanged. |
| `401 Unauthorized` | Missing, malformed, expired, revoked, or wrong-audience token; disabled/deleted owner | Generate or authorize a new user-specific credential and inspect the `WWW-Authenticate` header. |
| `403` request host/origin error | Host or browser Origin is not exactly allowlisted | Correct proxy Host handling or the exact allowed host/origin. |
| `413` | Proxy or module body limit exceeded | Reduce the request or intentionally raise both proxy and module limits. |
| `429` | Authentication-IP or per-token rate limit exceeded | Wait for `Retry-After`, fix retry loops, or deliberately adjust the appropriate limit. |
| `503 MCP Server is disabled` | `MCP_SERVER_ENABLED=false` or stale configuration cache | Enable it and clear FreeScout's cache. |

Authentication intentionally does not reveal whether a token, account state, expiry, revocation, or policy caused a `401`.

## Client connects but tools are missing

- Write tools require `MCP_SERVER_MUTATIONS_ENABLED=true`, the administrator **Write tools** setting, and `mcp:write` for OAuth.
- Knowledge Base tools require an active compatible module and mailbox-scoped tables.
- A regular user's mailbox memberships and assigned-only permission constrain the catalogue's results, not usually the catalogue names.
- Reconnect the client after changing module availability or scopes; catalogue responses have a private cache hint.
- Codex: run `codex mcp list` and verify the bearer environment variable is visible to the process that launches Codex.
- Claude Code: use `/mcp`; an unset `${FREESCOUT_MCP_TOKEN}` produces a missing-variable warning.

## OAuth failures

- `invalid_redirect_uri`: use the exact registered HTTPS callback or approved loopback callback. Codex may select a variable port on its numeric loopback callback.
- `invalid_grant`: the code/refresh token is expired, consumed, revoked, reused, for another client/resource, or belongs to an ineligible user. Start a new login.
- `invalid_scope`: request only `mcp:read`, `mcp:write`, and optionally `offline_access`; refresh cannot add scopes.
- Issuer mismatch: make `APP_URL`, `MCP_SERVER_OAUTH_ISSUER`, proxy scheme/host, discovered metadata, and authorization response agree exactly.
- CIMD retrieval failure: use public HTTPS metadata with no redirects, credentials, query, fragment, private/reserved address, or oversized document; ensure its `client_id` equals the document URL.
- DCR failure: enable `MCP_SERVER_OAUTH_DCR_ENABLED`, or switch the client to CIMD/pre-registration.

## Package and activation failures

The ZIP must extract to `Modules/McpServer`, include `Public`, and include `vendor/autoload.php`. If activation reports a missing table, run FreeScout's **Migrate DB** tool. Follow FreeScout's official module troubleshooting for ownership, cache, module symlinks, and logs: [FreeScout Modules troubleshooting](https://github.com/freescout-help-desk/freescout/wiki/FreeScout-Modules#4-troubleshooting).

## Safe diagnostics

Record HTTP status, response error code, request method, MCP method, timestamp, client version, module version, and a token's non-secret display prefix. Never record the Authorization header, plaintext token, message/draft bodies, customer addresses, OAuth codes, access tokens, or refresh tokens.

