# Authentication security design

## Token format and storage

A personal token has the form `fsmcp_<selector>_<secret>`:

- The selector is 96 random bits and permits one indexed database lookup. It is not treated as a secret.
- The secret is 256 random bits and is displayed only in the redirect immediately after creation.
- The database stores an HMAC-SHA-256 of the secret, keyed by `MCP_SERVER_TOKEN_PEPPER` or, by default, FreeScout's `APP_KEY`.
- Comparisons use `hash_equals`. Unknown selectors still perform equivalent keyed-hash work.
- Models hide `secret_hash` from array and JSON representations.

A database export therefore contains neither usable bearer credentials nor an unkeyed verifier. Changing the pepper deliberately invalidates all issued tokens.

## Authentication rules

Every enabled MCP POST must supply exactly one `Authorization: Bearer` credential. Authentication fails when:

- the header or token format is malformed;
- the selector or secret does not match;
- the token is expired or revoked;
- personal tokens have been disabled globally;
- regular-user tokens are disabled and the owner is not an administrator;
- the owner is disabled, deleted, or is a non-human/robot account.

Successful authentication places both the user and token in `McpRequestContext`, attaches them to the Illuminate request, and sets FreeScout's current authenticated user. Read tools use this context and FreeScout's existing authorization behavior for every call.

Browser preflight requests do not authenticate, but still pass through the configured CORS and host protections. The endpoint remains disabled unless `MCP_SERVER_ENABLED=true`.

## Abuse controls

- Failed authentication is limited by source IP, defaulting to 30 attempts per minute.
- Authenticated traffic is limited by token ID, defaulting to 120 requests per minute.
- Rejections return JSON-RPC-shaped errors with `Cache-Control: no-store`.
- `401` responses include a Bearer challenge but never disclose whether a selector, secret, user, expiry, revocation, or policy check failed.
- The request context is cleared at the beginning of every request.
- Last-used metadata is updated at most every five minutes to avoid a write on every tool call.

## Management rules

- Users can issue tokens only for themselves.
- Administrators can view token metadata and revoke tokens for any non-deleted user.
- Administrators cannot retrieve plaintext or mint a token while impersonating another user.
- Token lifetime is an administrator-controlled policy applied when a token is created. Setting it to zero permits non-expiring tokens.
- Disabling personal tokens or regular-user access takes effect during authentication and does not require deleting records.

## Read-data isolation

Read tools build their database scope from the authenticated user's current mailbox IDs and assigned-only permission before applying filters or pagination. Direct ticket reads are also checked through FreeScout's conversation policy. Missing and unauthorized ticket IDs deliberately share one error, list results have no global total, unpublished threads are omitted, and cursors are created only from authorized result IDs.

Customer results require at least one authorized conversation. Regular-user lookup returns only the caller; administrators retain FreeScout's administrator visibility. Knowledge Base queries are registered only for an active module with a recognized mailbox-scoped schema and apply the same mailbox boundary.

OAuth is intentionally out of scope for this phase. It will be added as a separate authorization mechanism without weakening these personal-token rules.
