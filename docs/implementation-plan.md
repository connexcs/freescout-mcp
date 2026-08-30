# Implementation plan

The module is deliberately split so that an unauthenticated foundation never contains data-access tools.

## Phase 0 — compatibility spike (complete)

- Confirm PHP 8.1, FreeScout 1.8.237, and MCP SDK dependency compatibility.
- Select the final `2026-07-28` stateless lifecycle.
- Prove `server/discover` and `tools/list` after loading FreeScout's dependency graph.
- Record framework bridge, dependency pinning, client, and OAuth decisions.

Acceptance: the standalone, PHP 8.1, and FreeScout-runtime smoke tests pass. See `phase-0-compatibility.md`.

## Phase 1 — module and transport foundation (complete)

- Add the FreeScout module manifest, service provider, config, and route.
- Adapt Illuminate requests/responses to the official SDK's PSR HTTP boundary.
- Add host validation, opt-in CORS, body-size limits, private cache hints, and modern-header validation.
- Keep the endpoint disabled by default and expose no functional tools.
- Add tests, CI, AGPL licensing, and production ZIP packaging.

Acceptance: FreeScout discovers the module, MCP discovery advertises only tools, the tool catalogue is empty, legacy initialization is rejected, and the release archive contains production dependencies.

## Phase 2 — per-user authentication

- Add a migration and model for named MCP tokens linked to a FreeScout user.
- Generate at least 256 bits of randomness; show plaintext once and store only a keyed hash plus a non-secret display prefix.
- Support expiry, revocation, last-used timestamp, and administrator disable/revoke controls.
- Add bearer-token middleware that resolves the active FreeScout user for every request and returns standards-based `401` responses.
- Add an account UI for token creation/revocation and an administrator policy switch.
- Add rate limiting and security-focused tests before enabling the endpoint in documentation.

Acceptance: two users receive different permission contexts; copied database data cannot be used as a bearer token; revoked, expired, disabled-user, and malformed credentials fail closed.

## Phase 3 — permission-safe read tools

- Introduce a request context carrying the authenticated FreeScout user.
- Implement deterministic, paginated tools corresponding to the established integration: ticket/conversation lookup, ticket context and threads, ticket search, and mailbox listing.
- Add customer and user lookup only where FreeScout's existing permissions permit it.
- If `knowledgebase` is installed and active, conditionally register article/category search and read tools. Do not make the module a hard dependency.
- Use FreeScout models and scopes directly; centralize mailbox/conversation authorization and field redaction instead of relying on clients to filter results.
- Make the user-specific tool catalogue private and invalidate its cache when permissions or optional-module availability changes.

Acceptance: cross-mailbox and cross-user authorization tests prove inaccessible records cannot be inferred by ID, search, counts, errors, or pagination.

## Phase 4 — controlled mutation tools and auditing

- Add note, status/assignment update, and draft-reply tools matching the useful operations in the existing MCP project.
- Keep sending a reply separate from drafting and require explicit confirmation semantics for externally visible actions.
- Re-run authorization inside the transaction, validate state transitions, and use FreeScout domain behavior so notifications and side effects remain consistent.
- Record actor, token, tool, target, outcome, and safe argument metadata in an MCP audit log. Never log bearer tokens or unnecessary message content.
- Add idempotency protection where retries could duplicate side effects.

Acceptance: every mutation has allow/deny, validation, retry, rollback, and audit tests.

## Phase 5 — OAuth authorization

- Implement MCP authorization-server metadata and the current OAuth profile required by hosted clients.
- Reuse the authenticated FreeScout browser session for consent and account selection.
- Treat FreeScout's OAuth & Social Login module as an optional way to authenticate that browser session, not as an authorization server.
- Issue short-lived access tokens with refresh-token rotation, revocation, exact redirect validation, PKCE, issuer validation, and client metadata handling required by the then-current MCP specification.
- Keep personal bearer tokens available for Codex/Claude configurations that support static headers.

Acceptance: complete interoperability tests with the current Codex and Claude connector flows, plus OAuth negative/security cases.

## Cross-cutting release gates

- Update against the latest final MCP specification and official PHP SDK before each release.
- Test the supported PHP matrix and the latest supported FreeScout release.
- Run dependency audit, protocol smoke tests, permission regression tests, and release-archive validation.
- Document every schema migration, configuration default, and security-relevant behavior change.
