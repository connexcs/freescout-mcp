# Changelog

## Unreleased

### Fixed

- OAuth: the consent redirect is no longer blocked by FreeScout's Content-Security-Policy.
  The app layout emits `form-action 'self'`, and browsers enforce `form-action` across
  redirects, so approving consent returned a correct 302 that the browser then refused to
  follow. The authorization code was never delivered and the consent page appeared to do
  nothing. The validated redirect origin is now registered on FreeScout's `csp.form_action`
  filter. Affected every browser-based MCP client.
- OAuth: CIMD client metadata is no longer unreachable on IPv4-only hosts. `CURLOPT_RESOLVE`
  entries are keyed by `host:port`, so emitting one entry per DNS answer made the AAAA record
  replace the A record rather than adding a fallback. All addresses are now passed in a single
  comma-separated entry.

## 1.0.0 — 2026-09-01

First stable release.

- Native PHP MCP `2026-07-28` stateless HTTP endpoint inside FreeScout.
- User-specific personal tokens with one-time plaintext display, keyed-hash storage, expiry, revocation, and administrator policy controls.
- OAuth authorization code with S256 PKCE, consent, scoped short-lived access tokens, rotating refresh tokens, family revocation, CIMD, and DCR fallback.
- Permission-scoped ticket, thread, mailbox, customer, user, and optional Knowledge Base read tools.
- Opt-in internal-note, ticket-update, and unsent-draft tools with transaction-safe idempotency and content-free audits.
- Host/origin controls, rate limiting, bounded request/output sizes, enumeration resistance, and disabled/deleted-user enforcement.
- Codex and Claude Code configuration fixtures, protocol/security qualification, deployment documentation, CI, and validated production ZIP packaging.

