# Phase 6 security and interoperability qualification

Date: 2026-08-31

The runtime, migration, role/isolation/audit, and OAuth fixtures passed on PHP 8.4.15 against fresh FreeScout commit `4080415870a979984b33bdce6a1b7848f17cadd9`. The normal CI matrix continues to cover PHP 8.1 through 8.4.

## Security matrix

| Boundary | Automated evidence |
| --- | --- |
| Administrator | Sees both fixture mailboxes, tickets, and Knowledge Base content. |
| Ordinary mailbox member | Sees only the member mailbox, related customers, tickets, and Knowledge Base records. |
| Assigned-only user | Sees the assigned fixture ticket but not another ticket in the same mailbox. |
| No-access user | Receives no tickets, mailboxes, customers, or Knowledge Base results. |
| Disabled/deleted owner | Personal-token HTTP fixture and token-policy tests reject both states. |
| Expired/revoked token | Personal-token HTTP fixture rejects both states on the next request. |
| Scope failure | Read-only OAuth principals cannot mutate; refresh tokens cannot escalate scope; denial is audited without content. |
| Enumeration | Missing and inaccessible IDs return the same error; inaccessible search terms return no item, total, or cursor. |
| Request target | Exact Host and Origin allowlists reject malformed, injected, unconfigured, prefix/suffix, userinfo, and path variants. |
| Resource bounds | Transport rejects request bodies above the configured limit; tool handling rejects encoded output above its independent limit. |
| Audit privacy | Sentinels prove audit rows omit bearer tokens, note/draft/denied bodies, and recipient addresses. |

The FreeScout database fixtures are `scripts/freescout-auth-integration.php`, `scripts/freescout-read-integration.php`, and `scripts/freescout-oauth-integration.php`. Unit and transport coverage runs with `composer check`.

## Client configuration

The checked fixtures are `tests/Interop/codex-config.toml` and `tests/Interop/claude.mcp.json`. Both reference `FREESCOUT_MCP_TOKEN`; neither embeds a credential. On the qualification date:

- Codex CLI 0.151.0 parsed the URL and `bearer_token_env_var` and reported Bearer-token authentication enabled.
- Claude Code 2.1.252 parsed the project configuration, expanded the environment-backed Authorization header configuration, and reported the server pending normal project approval.

These checks validate client configuration and header support without committing or printing a real token.

## Protocol and Inspector

Direct protocol tests cover stateless discovery and catalogue calls, removal of `initialize`, required request metadata, malformed JSON, and contradictory method/version headers.

MCP Inspector 2.4.0 successfully sends the configured bearer header, but its HTTP client begins with the older initialization flow and omits the `2026-07-28` request metadata. The server returns the expected `400` instead of silently accepting an ambiguous protocol. `composer interop-inspector` pins and reproduces this probe; it will also pass if a future Inspector completes the modern call successfully.

The module intentionally does not add a legacy Streamable HTTP/session route because the project selected modern compatibility only.
