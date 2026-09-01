# FreeScout MCP Server

A native FreeScout module that exposes FreeScout capabilities through the Model Context Protocol (MCP). It runs inside the FreeScout PHP application and uses FreeScout's models, database, and permission model directly.

## Current status

Version 1.0.0 is the first stable release. Phases 0 through 8 are complete, with OAuth delivered ahead of its original sequence. The module provides a modern stateless HTTP endpoint, per-user personal and OAuth authentication, permission-aware read tools, optional Knowledge Base access, and opt-in audited mutation tools.

The endpoint is disabled by default. Once enabled, every POST requires a valid personal or audience-bound OAuth access token belonging to an active, policy-eligible FreeScout user.

## Compatibility

- PHP 8.1 or newer
- FreeScout 1.8.237 or newer, on its Laravel 5.5-based module runtime
- MCP specification `2026-07-28`, using the stateless request/response lifecycle
- Official PHP MCP SDK `mcp/sdk` 0.8.1
- Codex and modern Claude clients that support the `2026-07-28` lifecycle

See [the documentation index](docs/index.md), [phase 0 compatibility record](docs/phase-0-compatibility.md), and [implementation plan](docs/implementation-plan.md).

## Documentation

- [Installation and upgrade](docs/installation.md)
- [Server configuration](docs/configuration.md)
- [Codex, Claude Code, token, and OAuth setup](docs/clients.md)
- [Reverse-proxy deployment](docs/reverse-proxy.md)
- [Tool and scope reference](docs/tools-and-scopes.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Security design](docs/security.md)

## Development

```bash
composer install
composer check
```

For a local protocol smoke test:

```bash
composer smoke
```

To exercise the MCP SDK after loading a real FreeScout dependency graph:

```bash
php scripts/freescout-runtime-smoke.php /path/to/freescout
```

The read/write, OAuth, and migration regression fixtures must use an in-memory testing database:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  php scripts/freescout-read-integration.php /path/to/freescout

APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  php scripts/freescout-migration-smoke.php /path/to/freescout

APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  php scripts/freescout-oauth-integration.php /path/to/freescout
```

## Installing a development checkout

1. Install production dependencies with `composer install --no-dev --optimize-autoloader`.
2. Place this directory at `Modules/McpServer` in a FreeScout installation.
3. Ensure the empty `Public` directory is retained.
4. Activate **MCP Server** under **Manage → Modules**.
5. Keep `MCP_SERVER_ENABLED=false` until an authenticated development test is required.

When explicitly enabled, the endpoint is `<FREESCOUT_URL>/mcp` (including FreeScout's configured subdirectory, if any).

For the production ZIP, ownership, migrations, verification, upgrades, rollback, and uninstall guidance, use [Installation and upgrade](docs/installation.md).

The host allowlist defaults to the hostname in `APP_URL`. Extra hosts and browser origins are comma-separated:

```dotenv
MCP_SERVER_ENABLED=false
MCP_SERVER_MUTATIONS_ENABLED=false
MCP_SERVER_ALLOWED_HOSTS=support.example.com
MCP_SERVER_ALLOWED_ORIGINS=https://example-client.test
MCP_SERVER_MAX_BODY_BYTES=1048576
MCP_SERVER_MAX_TOOL_OUTPUT_BYTES=1048576
MCP_SERVER_CATALOG_TTL_MS=300000
MCP_SERVER_RATE_LIMIT=120
MCP_SERVER_AUTH_RATE_LIMIT=30
MCP_SERVER_OAUTH_ENABLED=true
MCP_SERVER_OAUTH_ISSUER=https://support.example.com
MCP_SERVER_OAUTH_DCR_ENABLED=true
MCP_SERVER_OAUTH_ACCESS_TOKEN_LIFETIME=3600
MCP_SERVER_OAUTH_REFRESH_TOKEN_LIFETIME=2592000
MCP_SERVER_OAUTH_CIMD_CACHE=3600
```

`MCP_SERVER_TOKEN_PEPPER` is optional and defaults to `APP_KEY`. If set, it must be a stable, secret value. Changing either the configured pepper or the fallback `APP_KEY` invalidates every existing MCP token.

## Personal tokens

1. Activate the module so FreeScout runs the `mcpserver_tokens` migration.
2. As an administrator, open **Manage → Settings → MCP Server** to control personal-token availability, regular-user access, and the lifetime applied to new tokens.
3. Open **Your Profile → MCP Tokens**, name a token, and copy the displayed value immediately. Only its keyed hash is stored.
4. Set `MCP_SERVER_ENABLED=true` and clear FreeScout's configuration cache when ready.
5. Configure the client to send `Authorization: Bearer <token>` to the displayed endpoint.

Example stateless discovery request:

```bash
curl -sS https://support.example.com/mcp \
  -H "Authorization: Bearer $FREESCOUT_MCP_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  -d '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```

Administrators can inspect token metadata and revoke individual or all tokens from a user's MCP Tokens page, but cannot recover token plaintext or create a token on another user's behalf. See [the security design](docs/security.md).

## OAuth connections

Hosted clients can discover OAuth automatically from the `401` Bearer challenge and the module's protected-resource and authorization-server metadata. The user signs into FreeScout in the browser, sees the client identity, callback host, and requested scopes, then approves access to that FreeScout account. The OAuth & Social Login module may supply that browser login, but is not used as the MCP authorization server.

Set `APP_URL` and, when needed, `MCP_SERVER_OAUTH_ISSUER` to the stable public HTTPS origin before authorizing clients. OAuth supports preferred Client ID Metadata Documents and deprecated dynamic registration for current-client compatibility. Access defaults to `mcp:read`; clients must request `mcp:write` before mutation tools are advertised or callable. Users and administrators can revoke OAuth connections under **Profile → MCP Tokens**. See [the OAuth deployment and protocol reference](docs/oauth.md).

## Read tools

The authenticated catalogue includes ticket metadata, ticket context and published threads, ticket search, mailbox listing, customer search, and user search. Every query is constrained using the authenticated user's current FreeScout mailbox and assigned-ticket permissions before pagination. Inaccessible and missing ticket IDs produce the same result.

When the official `knowledgebase` module is active and its compatible mailbox-scoped tables are present, four additional article/category search and read tools are registered. No Knowledge Base package is required by this module. See [the read-tool reference and security rules](docs/read-tools.md).

## Mutation tools

Write tools are disabled by default and require both `MCP_SERVER_MUTATIONS_ENABLED=true` and the **Write tools** administrator setting. When enabled, users can add internal notes, update ticket status/assignment, and create unsent draft replies within their existing FreeScout permissions. Every call requires an idempotency key and produces a redacted audit record.

There is deliberately no send-reply tool. Creating a draft never sends mail or makes content customer-visible. See [the mutation-tool and audit reference](docs/mutation-tools.md).

## Packaging

Run `scripts/build-release.sh`. It creates and validates `build/McpServer-<version>.zip` with production Composer dependencies and a matching `.sha256` checksum, ready to unpack into FreeScout's `Modules` directory. See [the release process](docs/release.md).

## Interoperability qualification

`composer interop-config` validates token-free Codex `config.toml` and Claude Code `.mcp.json` fixtures. `composer interop-inspector` runs MCP Inspector 2.4.0 with an Authorization header against a local endpoint. Inspector currently starts the older initialization lifecycle, so the probe verifies that the modern-only server rejects its missing stateless metadata. Direct conformance tests cover the `2026-07-28` lifecycle until Inspector supports it.

See [the Phase 6 qualification record](docs/phase-6-security-interoperability.md) for the test matrix and current client results.

## Roadmap

- Phase 2 (complete): per-user bearer tokens, keyed hashes at rest, revocation, expiry, rate limits, and administrative controls
- Phase 3 (complete): read-only conversation, customer, mailbox, user, and optional Knowledge Base tools
- Phase 4 (complete): opt-in note, ticket-update, and unsent draft tools with idempotency and audit logging
- Phase 5 (complete): OAuth 2.1 discovery, browser consent, CIMD/DCR clients, PKCE, scoped access, refresh rotation, and revocation
- Phase 6 (complete): role and token-state security matrix, enumeration resistance, request/output bounds, audit redaction, and client/protocol interoperability probes
- Phase 7 (complete): installation, client, proxy, security, troubleshooting, and release documentation plus the validated versioned production ZIP
- Phase 8 (delivered early in Phase 5): OAuth authorization, consent, PKCE, rotation, revocation, CIMD/DCR, and FreeScout-session SSO

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
