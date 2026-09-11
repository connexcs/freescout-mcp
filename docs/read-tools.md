# Read tools

All tools are read-only, deterministic, and evaluated as the FreeScout user who owns the bearer token. Results use opaque keyset cursors and a maximum page size of 100.

## Core catalogue

| Tool | Purpose |
| --- | --- |
| `freescout_get_ticket` | Read metadata for one ticket. |
| `freescout_get_ticket_context` | Read ticket metadata and its newest published threads. |
| `freescout_get_ticket_threads` | Page through published threads, newest first. |
| `freescout_search_tickets` | Search accessible tickets with optional mailbox, customer, assignee, status, and—when Tags is available—exact tag-name filters. |
| `freescout_get_mailboxes` | List accessible mailboxes. |
| `freescout_search_customers` | Search customers linked to accessible tickets. |
| `freescout_search_users` | Search users allowed by FreeScout's user-view policy; regular users can retrieve only themselves. |

Ticket results contain operational metadata and a short preview. When a compatible Tags schema is available, ticket metadata also contains its authorized tag IDs/names/colors. Tag metadata for ticket-search pages is batch-loaded rather than queried once per ticket. Thread bodies are converted to plain text and capped at 50,000 characters. Attachment metadata is returned, but attachment contents, storage paths, message headers, BCC recipients, internal model attributes, and mailbox credentials are never returned.

## Optional Tags catalogue

When a compatible FreeScout Tags schema is available, the server additionally registers:

- `freescout_get_ticket_tags`
- `freescout_search_tags`

Tag searches return only tags that are associated with at least one conversation the authenticated user can actually view under the final FreeScout conversation policy. Global tag counters are deliberately omitted because they could reveal aggregate activity from inaccessible conversations.

Supplying a `tag` filter to `freescout_search_tickets` while Tags support is unavailable is rejected. It is never silently treated as an unfiltered search.

## Authorization behavior

- Administrators use FreeScout's administrator mailbox visibility.
- Regular users are restricted to mailboxes connected to their account.
- The **User can see only assigned conversations** permission additionally restricts tickets to those assigned to or created by that user.
- FreeScout's conversation policy is checked again for direct ticket reads and tag visibility.
- A caller receives `Ticket not found.` for both an absent ID and an inaccessible ID.
- Authorization is applied before search filters, ordering, and cursor pagination. Responses never include global totals.
- Only published threads are exposed; drafts, hidden threads, raw mail headers, and original unredacted bodies are omitted.

## Optional Knowledge Base catalogue

If the `knowledgebase` module is active and compatible mailbox-scoped article and category tables are detected, the server also registers:

- `freescout_search_kb_articles`
- `freescout_get_kb_article`
- `freescout_search_kb_categories`
- `freescout_get_kb_category`

The adapter recognizes the standard `kb_*` table names and defensive historical variants. It deliberately stays disabled when it cannot prove that both articles and categories have a `mailbox_id`, title, and body mapping. This fail-closed behavior prevents a future or customized Knowledge Base schema from accidentally exposing records across mailboxes.
