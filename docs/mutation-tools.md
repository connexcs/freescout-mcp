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
| `freescout_send_reply` | Creates and sends a published customer-visible reply. `confirm_send: true` is mandatory on every call. |
| `freescout_create_ticket` | Creates a new email conversation and sends its initial message. `confirm_send: true` is mandatory. |
| `freescout_set_ticket_tags` | Replaces the complete tag set on a ticket when the FreeScout Tags module/schema is available. Unknown tag names may be created. |

Bodies are accepted as plain text, escaped before storage, and limited to 100,000 characters. CC/BCC lists accept at most 50 validated addresses each. The assignee must be available to the ticket's mailbox.

`freescout_create_ticket` accepts either `customer_id`, `customer_email`, or both. If only an email is supplied, the existing FreeScout customer is reused when found and otherwise a customer may be created. Ticket creation and reply sending are intentionally distinct from draft creation because they can cause customer-visible email delivery.

Every mutation call requires an `idempotency_key` of 8–128 safe characters. Retrying the same tool, token, key, and arguments returns the original result with `replayed: true`. Reusing a key with different arguments is rejected. The stored request fingerprint is keyed with the module token pepper; message content is not stored in the idempotency row.

## Customer-visible confirmation

`freescout_send_reply` and `freescout_create_ticket` require the literal boolean `confirm_send: true` on each call. There is no implicit conversion from a draft to a sent reply, and `freescout_create_draft_reply` continues to guarantee that it does not send mail.

The confirmation argument is part of the idempotent request fingerprint. Clients must not retry a send with a new idempotency key after an uncertain response; reuse the original key so the server can replay the known result instead of initiating a second mutation.

## Transactions and authorization

Existing-ticket mutations load the conversation with a row lock and check FreeScout's `update` policy inside the mutation transaction. Ticket creation verifies access to the requested mailbox and validates any requested assignee against that mailbox. Requested transitions are validated before the corresponding domain change.

An inaccessible ticket and a missing ticket both return `Ticket not found.` Tags are exposed only when a compatible Tags schema is detected. Tag reads are constrained to tags associated with conversations visible to the authenticated user.

Published replies and newly created conversations use FreeScout's thread/conversation domain behavior so normal reply/creation events and folder updates occur. Mutations, idempotency state, and audit records are transactionally coordinated by the existing mutation executor.

## Audit data

`mcpserver_audit_logs` records successful, replayed, denied, validation-failed, and internal-failure outcomes. It stores actor/token identifiers, tool and target identifiers, the idempotency key, an error classification, and safe metadata such as body length, requested status, assignee ID, recipient count, tag count, and whether a customer-visible operation was explicitly confirmed.

Bearer tokens, message bodies, note text, email addresses, tag contents, and unnecessary customer data are not logged. Audit retention is operator-managed; deleting old audit and completed idempotency rows does not affect FreeScout tickets.
