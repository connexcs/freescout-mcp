# Client configuration and token walkthrough

The endpoint is the public FreeScout URL followed by `/mcp`, including any configured FreeScout subdirectory.

## Create a personal MCP token

1. Ask an administrator to enable personal tokens under **Manage → Settings → MCP Server**.
2. Sign in as the FreeScout user whose permissions the client should use.
3. Open **Your Profile → MCP Tokens**.
4. Enter a descriptive name such as `Codex on workstation` and select **Create token**.
5. Copy the `fsmcp_…` value immediately. FreeScout never displays it again and stores only a keyed verifier.
6. Put it in a private environment variable named `FREESCOUT_MCP_TOKEN`.
7. Revoke it from the same page when the device or configuration is retired.

Each person should use their own token. Do not share an administrator token, put a token directly in client configuration, or reuse FreeScout's system API key.

## Codex with a personal token

Add this to `~/.codex/config.toml`:

```toml
[mcp_servers.freescout]
url = "https://support.example.com/mcp"
bearer_token_env_var = "FREESCOUT_MCP_TOKEN"
required = true
default_tools_approval_mode = "writes"
```

Export the token in the environment that launches Codex:

```bash
read -rsp 'FreeScout MCP token: ' FREESCOUT_MCP_TOKEN && echo
export FREESCOUT_MCP_TOKEN
codex mcp list
```

`default_tools_approval_mode = "writes"` asks before tools that are not marked read-only. The server still independently enforces FreeScout authorization and both write-tool gates. The official Codex MCP reference documents `bearer_token_env_var`, `required`, tool approval modes, and `codex mcp list`: [OpenAI Codex MCP configuration](https://developers.openai.com/codex/mcp/).

## Codex with OAuth

Omit `bearer_token_env_var`:

```toml
[mcp_servers.freescout]
url = "https://support.example.com/mcp"
required = true
default_tools_approval_mode = "writes"
```

Then run:

```bash
codex mcp login freescout
```

Complete FreeScout sign-in and consent in the browser. Use `codex mcp login freescout --oauth-client-registration dcr` only when automatic registration selection fails and DCR remains enabled. Do not configure both a personal bearer environment variable and OAuth unless you deliberately want the personal token to take precedence.

## Claude Code with a personal token

Create `.mcp.json` at the desired Claude project scope:

```json
{
  "mcpServers": {
    "freescout": {
      "type": "http",
      "url": "https://support.example.com/mcp",
      "headers": {
        "Authorization": "Bearer ${FREESCOUT_MCP_TOKEN}"
      }
    }
  }
}
```

Launch Claude Code with `FREESCOUT_MCP_TOKEN` set, approve the project server when prompted, and use `/mcp` to inspect its status. Claude Code officially supports `${VAR}` expansion in HTTP headers: [Claude Code MCP environment expansion](https://docs.anthropic.com/en/docs/claude-code/mcp#environment-variable-expansion-in-mcpjson).

## Claude Code with OAuth

Remove the custom Authorization header:

```json
{
  "mcpServers": {
    "freescout": {
      "type": "http",
      "url": "https://support.example.com/mcp",
      "oauth": {
        "scopes": "mcp:read"
      }
    }
  }
}
```

Use `/mcp` and authenticate. For mutation tools, the client must explicitly request `mcp:write` and the server-side mutation gates must be enabled:

```json
"oauth": { "scopes": "mcp:read mcp:write" }
```

Claude Code documents `oauth.scopes` as a space-separated scope restriction and uses protected-resource metadata for discovery: [Claude Code OAuth scope configuration](https://docs.anthropic.com/en/docs/claude-code/mcp#restrict-oauth-scopes).

## Credential precedence and revocation

- Personal-token configurations send the named environment variable as an Authorization bearer credential.
- OAuth configurations store short-lived access credentials in the client and rotate refresh tokens through FreeScout.
- A disabled or deleted FreeScout user loses access on the next request.
- Revoking a personal token or OAuth connection takes effect on the next request.
- Changing mailbox membership or assigned-only permissions changes subsequent tool results without issuing a new credential.
