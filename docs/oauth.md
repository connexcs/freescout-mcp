# OAuth deployment and protocol reference

## Discovery and endpoints

With both `MCP_SERVER_ENABLED` and `MCP_SERVER_OAUTH_ENABLED` enabled, the module publishes:

- `/.well-known/oauth-protected-resource` (and the `/mcp` path-specific variant)
- `/.well-known/oauth-authorization-server`
- `/mcp/oauth/authorize`
- `/mcp/oauth/token`
- `/mcp/oauth/revoke`
- `/mcp/oauth/register` while deprecated DCR compatibility is enabled

All paths include FreeScout's configured subdirectory. A `401` from `/mcp` includes the protected-resource metadata URL and `mcp:read` scope.

The issuer defaults to `APP_URL` and can be made explicit with `MCP_SERVER_OAUTH_ISSUER`. It must be a stable public HTTPS URL and must exactly match the issuer clients discover. If FreeScout is installed below a URL path, confirm that the reverse proxy exposes the RFC 8414 well-known URL expected for that issuer; an origin-level OAuth issuer is the simplest deployment.

## Client registration

The authorization server advertises Client ID Metadata Document support. A CIMD client ID must be an HTTPS URL with a non-root path and no credentials, query, or fragment. The JSON document must repeat that URL exactly as `client_id`, provide `client_name` and `redirect_uris`, and use `token_endpoint_auth_method: none`.

Dynamic Client Registration is retained for Codex/Claude clients that have not migrated to CIMD. Disable it with `MCP_SERVER_OAUTH_DCR_ENABLED=false` once it is no longer needed. Only public clients using the authorization-code grant are accepted; no client secret is issued.

## Scopes and token lifecycle

`mcp:read` is the default and permits only the user's existing read capabilities. `mcp:write` also requires the global write-tool gates and the user's underlying FreeScout permissions. Requesting write automatically includes read. The authorization-server metadata also advertises `offline_access` for clients that use it to request durable refresh capability; it is intentionally absent from protected-resource metadata and `401` challenges because it is not required to access the MCP resource.

Authorization and token requests must carry the exact MCP endpoint as `resource`. Authorization requires S256 PKCE and redirects are compared as exact strings. Access tokens default to 3,600 seconds and refresh tokens to 2,592,000 seconds; configure the lifetimes with the corresponding environment variables. Refresh tokens rotate, scope cannot increase during refresh, and detected reuse revokes the whole family.

The plaintext for every OAuth credential is returned only to the client. The database stores a selector and a keyed HMAC verifier. Users and administrators can inspect and revoke connection families from the existing MCP Tokens profile page.

## Browser login

The authorization endpoint uses FreeScout's normal `web` and `auth` middleware. An unauthenticated user is sent through the existing login flow and returned to consent. If the OAuth & Social Login module is installed, it can participate in that login exactly as it does elsewhere in FreeScout; the MCP module does not use its provider tokens and does not treat it as an authorization server.
