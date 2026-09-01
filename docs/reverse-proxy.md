# Reverse-proxy deployment

The MCP endpoint is stateless HTTPS request/response traffic. It does not require WebSockets, Server-Sent Events, sticky sessions, a GET stream, or a separate daemon.

## Required behavior

A proxy or WAF must:

- preserve the original `Host` header or use a host listed in `MCP_SERVER_ALLOWED_HOSTS`;
- forward `Authorization`, `Content-Type`, `Accept`, `MCP-Protocol-Version`, `Mcp-Method`, and `Mcp-Name`;
- preserve POST request bodies and OPTIONS preflights;
- expose `APP_URL` and `MCP_SERVER_OAUTH_ISSUER` consistently over HTTPS;
- avoid caching `/mcp`, OAuth token/revocation endpoints, and authorization responses;
- allow the configured request-body limit; and
- apply rate limits by source IP without logging Authorization values or bodies.

Because the protocol exposes routing metadata in HTTP headers, gateways can route and meter without parsing JSON. The [MCP 2026-07-28 release notes](https://blog.modelcontextprotocol.io/posts/2026-07-28/) describe these required headers.

## Nginx in front of FreeScout

If an upstream proxy forwards only the MCP and OAuth paths to an existing FreeScout origin:

```nginx
location = /mcp {
    proxy_pass http://freescout_upstream;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Authorization $http_authorization;
    proxy_request_buffering on;
    proxy_buffering off;
    client_max_body_size 1m;
    proxy_read_timeout 65s;
    add_header Cache-Control "no-store" always;
}

location ^~ /.well-known/oauth- {
    proxy_pass http://freescout_upstream;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}

location ^~ /mcp/oauth/ {
    proxy_pass http://freescout_upstream;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Authorization $http_authorization;
    proxy_buffering off;
    add_header Cache-Control "no-store" always;
}
```

Adjust paths when FreeScout runs in a subdirectory. Do not strip that subdirectory from the public URL unless `APP_URL`, OAuth issuer, resource, and callback endpoints are configured to match the rewritten public paths.

## Apache reverse proxy

```apache
ProxyPreserveHost On
ProxyPass        /mcp http://127.0.0.1:8080/mcp timeout=65
ProxyPassReverse /mcp http://127.0.0.1:8080/mcp
RequestHeader set X-Forwarded-Proto "https"

<Location "/mcp">
    Header always set Cache-Control "no-store"
    LimitRequestBody 1048576
</Location>
```

Proxy the well-known and `/mcp/oauth/` paths to the same FreeScout origin when OAuth is enabled. Ensure `mod_proxy`, `mod_proxy_http`, `mod_headers`, and TLS are enabled.

## CORS

Codex CLI and Claude Code normally do not send a browser Origin header, so `MCP_SERVER_ALLOWED_ORIGINS` should usually remain empty. Add an origin only for a browser client you explicitly trust, using the exact scheme, host, and port. CORS is not authentication and does not replace bearer tokens.

## Proxy verification

Check the public URL, not the upstream URL:

```bash
curl -i -X OPTIONS https://support.example.com/mcp \
  -H 'Origin: https://approved-client.example' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: authorization,content-type,mcp-protocol-version,mcp-method'
```

Also run the authenticated discovery request from [Installation](installation.md). A `403` mentioning host or origin usually means the proxy's Host or the browser Origin does not match the module allowlists.

