# Authentication security design

## Token format and storage

A personal token has the form `fsmcp_<selector>_<secret>`:

- The selector is 96 random bits and permits one indexed database lookup. It is not treated as a secret.
- The secret is 256 random bits and is displayed only in the redirect immediately after creation.
- The database stores an HMAC-SHA-256 of the secret, keyed by `MCP_SERVER_TOKEN_PEPPER` or, by default, FreeScout's `APP_KEY`.
- Comparisons use `hash_equals`. Unknown selectors still perform equivalent keyed-hash work.
- Models hide `secret_hash` from array and JSON representations.

A database export therefore contains neither usable bearer credentials nor an unkeyed verifier. Changing the pepper deliberately invalidates all issued tokens.

OAuth authorization codes, access tokens, and refresh tokens use separate `fsmcp_oc`, `fsmcp_oa`, and `fsmcp_or` prefixes but the same 96-bit selector, 256-bit secret, and keyed-hash storage design. Codes expire after five minutes and are single-use. Access tokens default to one hour; refresh tokens default to 30 days and rotate on every use. Reuse of a rotated refresh token revokes its complete family.

## Authentication rules

Every enabled MCP POST must supply exactly one `Authorization: Bearer` credential. Authentication fails when:

- the header or token format is malformed;
- the selector or secret does not match;
- the token is expired or revoked;
- personal tokens have been disabled globally;
- regular-user tokens are disabled and the owner is not an administrator;
- the owner is disabled, deleted, or is a non-human/robot account.

Successful authentication places both the user and token in `McpRequestContext`, attaches them to the Illuminate request, and sets FreeScout's current authenticated user. Read tools use this context and FreeScout's existing authorization behavior for every call.

OAuth access tokens are additionally bound to the exact canonical MCP endpoint supplied through the RFC 8707 `resource` parameter. `mcp:read` is required for normal access; `mcp:write` gates mutation catalogue exposure and execution. Disabling personal tokens does not disable OAuth, while disabled/deleted/robot-account and regular-user policy checks apply to both mechanisms.

Browser preflight requests do not authenticate, but still pass through the configured CORS and host protections. The endpoint remains disabled unless `MCP_SERVER_ENABLED=true`.

Every request validates `Host` against `APP_URL` plus `MCP_SERVER_ALLOWED_HOSTS`, even when an `Origin` header is present. Browser origins must exactly match `MCP_SERVER_ALLOWED_ORIGINS`; malformed origins, credentials in origins, paths, prefixes, and suffix lookalikes fail closed.

## Abuse controls

- Failed authentication is limited by source IP, defaulting to 30 attempts per minute.
- Authenticated traffic is limited by token ID, defaulting to 120 requests per minute.
- Rejections return JSON-RPC-shaped errors with `Cache-Control: no-store`.
- `401` responses include a Bearer challenge, protected-resource metadata URL, and minimum read scope, but never disclose whether a selector, secret, user, expiry, revocation, or policy check failed.
- The request context is cleared at the beginning of every request.
- Last-used metadata is updated at most every five minutes to avoid a write on every tool call.
- Request bodies and encoded tool results are independently capped by `MCP_SERVER_MAX_BODY_BYTES` and `MCP_SERVER_MAX_TOOL_OUTPUT_BYTES`, both 1 MiB by default.

## Management rules

- Users can issue tokens only for themselves.
- Administrators can view token metadata and revoke tokens for any non-deleted user.
- Administrators cannot retrieve plaintext or mint a token while impersonating another user.
- Token lifetime is an administrator-controlled policy applied when a token is created. Setting it to zero permits non-expiring tokens.
- Disabling personal tokens or regular-user access takes effect during authentication and does not require deleting records.

## Read-data isolation

Read tools build their database scope from the authenticated user's current mailbox IDs and assigned-only permission before applying filters or pagination. Direct ticket reads are also checked through FreeScout's conversation policy. Missing and unauthorized ticket IDs deliberately share one error, list results have no global total, unpublished threads are omitted, and cursors are created only from authorized result IDs.

Customer results require at least one authorized conversation. Regular-user lookup returns only the caller; administrators retain FreeScout's administrator visibility. Knowledge Base queries are registered only for an active module with a recognized mailbox-scoped schema and apply the same mailbox boundary.

OAuth uses the same request context and database permission scopes, so it does not create a separate data-access path around these rules.

## OAuth flow controls

- Authorization requires an authenticated FreeScout browser session and fresh CSRF-protected consent; the pending request is stored server-side in that session.
- Only authorization-code and refresh-token grants for public clients are accepted. S256 PKCE, exact redirect matching, and the exact MCP resource are mandatory.
- Authorization responses include `iss`; discovery advertises issuer-response validation support.
- Redirects must be HTTPS, except HTTP loopback callbacks on `localhost`, `127.0.0.1`, or `::1`. The callback hostname is shown on consent, with a separate loopback warning.
- CIMD URLs must be HTTPS document paths. Fetches reject credentials, query/fragment components, IP literals, private/reserved DNS results, redirects, oversized bodies, invalid TLS, and mismatched document identities. DNS answers are pinned for the request to limit rebinding.
- DCR remains optional and can be disabled after clients migrate to CIMD. It creates public clients without secrets.
- RFC 7009 revocation is non-oracular. Revoking a refresh token or a connection in FreeScout revokes its entire token family.

## Mutation controls

Mutation tools require both the environment gate and administrator switch. Authorization is re-evaluated after locking the ticket inside the transaction. Each request reserves a key scoped to token and tool; an exact retry returns the stored response, while reusing the key for different arguments is rejected. Failed operations roll back their reservation and domain changes.

Audit rows contain actor ID, token ID, tool, target, outcome, idempotency key, error classification, and bounded metadata such as body length or recipient count. They do not contain bearer credentials, note text, draft text, or recipient addresses. Audit-storage failure aborts a successful mutation so an unaudited write cannot commit.

Scope-denied mutation attempts are recorded as `denied` with `insufficient_scope`, using only the same safe metadata allowlist.
