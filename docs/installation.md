# Installation and upgrade

## Requirements

- FreeScout 1.8.237 or newer
- PHP 8.1 or newer with `curl` and `fileinfo`
- HTTPS for every non-loopback deployment
- Shell or hosting-panel access to the FreeScout installation
- A database and file backup before installation or upgrade

The release ZIP already contains production Composer dependencies. Do not run Composer in FreeScout's root directory and do not replace FreeScout's own dependencies.

## Install the release ZIP

The archive contains one top-level `McpServer` directory. For a FreeScout installation at `/var/www/html`:

```bash
cd /var/www/html
unzip /path/to/McpServer-1.0.0.zip -d Modules
test -f Modules/McpServer/module.json
test -f Modules/McpServer/vendor/autoload.php
```

The resulting path must be `/var/www/html/Modules/McpServer/module.json`, not `/var/www/html/Modules/McpServer/McpServer/module.json`. Retain the empty `Public` directory because FreeScout expects it when activating custom modules.

Set ownership to the same account that owns the rest of FreeScout. In a conventional installation this is `www-data`:

```bash
chown -R www-data:www-data /var/www/html/Modules/McpServer
```

Then:

1. Open **Manage → Modules**.
2. Activate **MCP Server**. FreeScout discovers migrations during activation.
3. If the database tables were not created, use **Manage → System → Tools → Migrate DB**, or run `php artisan migrate --force` from the FreeScout root.
4. Open **Manage → Settings → MCP Server** and choose the token, OAuth, lifetime, and write-tool policies.
5. Add the environment settings described in [Configuration](configuration.md).
6. Run `php artisan freescout:clear-cache` after changing `.env`.
7. Leave `MCP_SERVER_ENABLED=false` until configuration and HTTPS are ready; enable it last.

FreeScout's official custom-module instructions likewise require unpacking into `/Modules` and activating from **Manage → Modules**: [FreeScout Modules](https://github.com/freescout-help-desk/freescout/wiki/FreeScout-Modules#3-installing-custom-modules).

## Verify installation

Create a personal token as described in [Client configuration](clients.md), place it in the current shell, and call discovery:

```bash
read -rsp 'FreeScout MCP token: ' FREESCOUT_MCP_TOKEN && echo
export FREESCOUT_MCP_TOKEN
curl --fail-with-body --silent --show-error https://support.example.com/mcp \
  -H "Authorization: Bearer $FREESCOUT_MCP_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: server/discover' \
  --data '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```

A successful response identifies `freescout-mcp` and advertises protocol `2026-07-28`. The hidden prompt keeps the value out of shell history. Never place it in a repository, command transcript, or support ticket.

## Upgrade

The module stores state only in FreeScout's database. It does not store configuration or tokens inside its module directory.

1. Back up the FreeScout database and the current `Modules/McpServer` directory.
2. Set `MCP_SERVER_ENABLED=false` and clear FreeScout's cache.
3. Replace the complete `Modules/McpServer` directory with the directory from the new ZIP. Do not merge old `vendor` contents into the new release.
4. Restore ownership and verify that `Public` and `vendor/autoload.php` are present.
5. Run `php artisan migrate --force` from the FreeScout root.
6. Run `php artisan freescout:clear-cache`.
7. Review the changelog and new configuration defaults.
8. Re-enable the endpoint and perform the discovery check.

Migrations preserve existing personal tokens, OAuth connections, idempotency rows, and audit rows. Never roll back only the database migrations while running newer module code. For a rollback, restore both the previous module directory and its matching database backup.

## Uninstall

Deactivate the module before removing its directory. Removing the code does not automatically delete MCP database tables. Retaining them permits later reinstallation without losing token metadata or audits; remove them only under an explicit data-retention decision and after a backup.
