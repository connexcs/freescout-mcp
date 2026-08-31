# Mutation tools and audit behavior

Mutation tools are absent from `tools/list` unless both controls are enabled:

```dotenv
MCP_SERVER_MUTATIONS_ENABLED=true
```

An administrator must also enable **Manage → Settings → MCP Server → Write tools**. Disabling either control removes the tools without affecting read access.

## Tools

| Tool | Behavior |
| --- | --- |
| `freescout_add_note` | Creates a published internal note using FreeScout's note domain behavior and events. |
| `freescout_update_ticket` | Changes status and/or assignee, creating FreeScout's normal line-item threads and events. `-1` unassigns. |
| `freescout_create_draft_reply` | Creates an unpublished reply draft and adds it to the Drafts folder. It never sends mail. |

Bodies are accepted as plain text, escaped before storage, and limited to 100,000 characters. CC/BCC lists on drafts accept at most 50 validated addresses each. The assignee must be active and available to the ticket's mailbox.

Every call requires an `idempotency_key` of 8–128 safe characters. Retrying the same tool, token, key, and arguments returns the original result with `replayed: true`. Reusing a key with different arguments is rejected. The stored request fingerprint is keyed with the module token pepper; message content is not stored in the idempotency row.

## Transactions and authorization

The ticket is loaded with a row lock and FreeScout's `update` policy is checked inside the transaction. All requested transitions are validated before the first domain change. An inaccessible ticket and a missing ticket both return `Ticket not found.` Assignment, status, thread, folder, event, idempotency, and successful audit changes commit together or roll back together.

No send tool is registered. If one is added later, it must remain separate from draft creation and require an explicit confirmation argument for every customer-visible call.

## Audit data

`mcpserver_audit_logs` records successful, replayed, denied, validation-failed, and internal-failure outcomes. It stores actor/token identifiers, tool and target identifiers, the idempotency key, an error classification, and safe metadata such as body length, requested status, assignee ID, or recipient count.

Bearer tokens, message bodies, note text, email addresses, and unnecessary customer data are not logged. Audit retention is operator-managed; deleting old audit and completed idempotency rows does not affect FreeScout tickets.
