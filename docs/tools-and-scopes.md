# Tool and scope reference

Every tool runs as the FreeScout user who owns the personal token or approved the OAuth connection. MCP scopes never grant mailbox or ticket permissions.

## Scopes

| Scope | Meaning |
| --- | --- |
| `mcp:read` | Discover and call read tools within current FreeScout permissions. |
| `mcp:write` | Discover and call mutation tools when both server write gates and FreeScout update permission also allow them. Requesting write includes read. |
| `offline_access` | OAuth compatibility scope indicating durable refresh use. It is not a resource permission and is not accepted as a replacement for read or write. |

Personal tokens carry read and write capability, but write tools remain absent unless both operator gates are enabled. OAuth tokens expose only their approved scopes.

## Core tools

| Tool | Kind | Required scope | Availability |
| --- | --- | --- | --- |
| `freescout_get_ticket` | Read | `mcp:read` | Always |
| `freescout_get_ticket_context` | Read | `mcp:read` | Always |
| `freescout_get_ticket_threads` | Read | `mcp:read` | Always |
| `freescout_search_tickets` | Read | `mcp:read` | Always |
| `freescout_get_mailboxes` | Read | `mcp:read` | Always |
| `freescout_search_customers` | Read | `mcp:read` | Always |
| `freescout_search_users` | Read | `mcp:read` | Always; regular users can retrieve only themselves |
| `freescout_add_note` | Write | `mcp:write` | Both write gates enabled |
| `freescout_update_ticket` | Write | `mcp:write` | Both write gates enabled |
| `freescout_create_draft_reply` | Write | `mcp:write` | Both write gates enabled; never sends |

All lists use opaque cursor pagination with a maximum page size of 100. Mutation calls require a unique 8–128 character idempotency key. See [Read tools](read-tools.md) and [Mutation tools](mutation-tools.md) for fields and behavior.

## Knowledge Base compatibility

The following tools appear only when the `knowledgebase` module is active and the adapter recognizes mailbox-scoped article and category tables:

| Tool | Required scope |
| --- | --- |
| `freescout_search_kb_articles` | `mcp:read` |
| `freescout_get_kb_article` | `mcp:read` |
| `freescout_search_kb_categories` | `mcp:read` |
| `freescout_get_kb_category` | `mcp:read` |

The Knowledge Base integration is optional. It has no separate installation switch and does not make the MCP module depend on the Knowledge Base module. If schema compatibility or mailbox ownership cannot be proven, all four tools remain absent. Activating or deactivating Knowledge Base changes the private user-specific catalogue after its cache hint expires or the client reconnects.
