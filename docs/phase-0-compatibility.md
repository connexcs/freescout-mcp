# Phase 0 compatibility record

Date: 2026-08-30

## Decision

Build the module on the official PHP MCP SDK 0.8.1 and expose only the modern MCP `2026-07-28` stateless HTTP lifecycle. The implementation target is PHP 8.1+ and FreeScout's existing Laravel 5.5 runtime.

The spike was performed against:

- FreeScout `dist` commit `4b17e534a714d9844e71eb3d050a9754511ecd83`
- `mcp/sdk` 0.8.1
- `nyholm/psr7` 1.8.2
- PHP 8.1 as the minimum supported runtime (verified locally on PHP 8.4 and in the CI matrix)

## Findings

### MCP lifecycle

The `2026-07-28` specification removes the initialization handshake and session identifier from the modern core. Each request carries its protocol version and client capabilities in `_meta`; HTTP requests also carry routable `MCP-Protocol-Version`, `Mcp-Method`, and, where applicable, `Mcp-Name` headers. `server/discover` replaces mandatory initialization and list responses include cache hints.

This maps cleanly to PHP-FPM and FreeScout: each `POST /mcp` can be handled independently and does not require an MCP session store, sticky routing, a long-running process, GET/SSE, or a daemon beside FreeScout.

### SDK

The official SDK provides `Builder::buildStateless()` and `StatelessHttpTransport`, with explicit support for `ProtocolVersion::V2026_07_28`. Its PHP requirement is `^8.1` and its PSR dependencies are compatible with the FreeScout dependency graph.

The SDK is pinned exactly in `composer.json`, following FreeScout's dependency guidance. Shared PSR packages are also pinned to the versions in the tested FreeScout distribution, and Symfony UID is pinned to its PHP 8.1-compatible 5.4 release line. This prevents a dependency solve on a newer development machine from putting PHP 8.4-only packages into the module. The module has its own Composer autoloader because FreeScout loads module providers before module `start.php` files.

One SDK 0.8.1 defect was found by the executable spike: its default registry container declares typed PSR-11 v2 methods although the SDK permits PSR Container 1.x. FreeScout pins PSR Container 1.0.0, so loading the SDK default class causes a PHP signature error. The builder now receives the module's small `LegacyCompatibleContainer`; the SDK default container is never loaded and FreeScout's container dependency is not upgraded.

### HTTP boundary

FreeScout uses Symfony HttpFoundation 3.4 through Laravel 5.5, while the current Symfony PSR-7 bridge releases target newer HttpFoundation versions and conflicting PSR HTTP message versions. The bridge is therefore not used.

The module instead uses `nyholm/psr7` and two small, auditable adapters:

- `Psr7RequestFactory` maps an Illuminate request to a PSR-7 server request.
- `LaravelResponseFactory` maps the SDK's PSR-7 response back to an Illuminate response.

This keeps SDK dependencies isolated and avoids upgrading or replacing any FreeScout framework component.

### Routing and security boundary

- One `POST|OPTIONS /mcp` route is registered without FreeScout's browser session or CSRF middleware.
- The endpoint is disabled by default until phase 2 authentication is available.
- Request bodies are capped at 1 MiB by default.
- A module request-target policy validates every request host against `APP_URL` plus an operator allowlist.
- Cross-origin browser access is denied unless explicit origins are configured.
- Cache hints are private because the eventual tool catalogue will be user-specific.
- No FreeScout data tools are registered in phase 1.

### Client compatibility

The transport is suitable for clients that implement the final `2026-07-28` stateless lifecycle. Codex and Claude are the target clients. Older clients that require `initialize`, `Mcp-Session-Id`, or legacy Streamable HTTP are intentionally outside the compatibility target.

OAuth remains a later phase. FreeScout's OAuth module is an OAuth client used for signing into or connecting FreeScout; it is not an MCP authorization server. A later authorization-server implementation can reuse the authenticated FreeScout user/session for its consent UI.

## Sources

- [MCP 2026-07-28 release](https://blog.modelcontextprotocol.io/posts/2026-07-28/)
- [Official PHP MCP SDK](https://github.com/modelcontextprotocol/php-sdk)
- [Official PHP SDK stateless example](https://php.sdk.modelcontextprotocol.io/examples/)
- [FreeScout module development guide](https://github.com/freescout-help-desk/freescout/wiki/Modules-Development)
- [FreeScout development guide](https://github.com/freescout-help-desk/freescout/wiki/Development-Guide)

## Outcome

The spike passed. No dependency or architectural blocker was found for a native PHP module. Phase 1 therefore uses the official SDK, a narrow PSR adapter, a single stateless route, and exact dependency versions.
