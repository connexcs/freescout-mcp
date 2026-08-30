# FreeScout MCP Server

A native FreeScout module that exposes FreeScout capabilities through the Model Context Protocol (MCP). It runs inside the FreeScout PHP application and uses FreeScout's models, database, and permission model directly.

## Current status

Phases 0 and 1 are complete: the repository contains the compatibility decision record, a loadable FreeScout module skeleton, and a modern stateless HTTP MCP endpoint. The endpoint currently advertises an empty tool catalogue. Authentication, per-user tokens, permissions, audit logging, and functional tools intentionally begin in later phases.

The endpoint is disabled by default. Do not enable it on a public system until the authentication phase has landed.

## Compatibility

- PHP 8.1 or newer
- FreeScout 1.8.237 or newer, on its Laravel 5.5-based module runtime
- MCP specification `2026-07-28`, using the stateless request/response lifecycle
- Official PHP MCP SDK `mcp/sdk` 0.8.1
- Codex and modern Claude clients that support the `2026-07-28` lifecycle

See [the phase 0 compatibility record](docs/phase-0-compatibility.md) for the dependency and transport decisions, and the [implementation plan](docs/implementation-plan.md) for subsequent phases and release gates.

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

## Installing a development checkout

1. Install production dependencies with `composer install --no-dev --optimize-autoloader`.
2. Place this directory at `Modules/McpServer` in a FreeScout installation.
3. Ensure the empty `Public` directory is retained.
4. Activate **MCP Server** under **Manage → Modules**.
5. Keep `MCP_SERVER_ENABLED=false` until an authenticated development test is required.

When explicitly enabled, the endpoint is `<FREESCOUT_URL>/mcp` (including FreeScout's configured subdirectory, if any).

The host allowlist defaults to the hostname in `APP_URL`. Extra hosts and browser origins are comma-separated:

```dotenv
MCP_SERVER_ENABLED=false
MCP_SERVER_ALLOWED_HOSTS=support.example.com
MCP_SERVER_ALLOWED_ORIGINS=https://example-client.test
MCP_SERVER_MAX_BODY_BYTES=1048576
MCP_SERVER_CATALOG_TTL_MS=300000
```

## Packaging

Run `scripts/build-release.sh`. It creates `build/McpServer-<version>.zip` containing production Composer dependencies, ready to unpack into FreeScout's `Modules` directory.

## Roadmap

- Phase 2: per-user bearer tokens, hashed at rest, revocation, expiry, and administrative controls
- Phase 3: read-only conversation, customer, mailbox, user, and optional Knowledge Base tools
- Phase 4: permission-safe mutation tools with explicit confirmation semantics and audit logging
- Phase 5: OAuth authorization for hosted clients, without treating FreeScout's existing OAuth client module as an authorization server

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
