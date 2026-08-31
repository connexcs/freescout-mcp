<?php

namespace Modules\McpServer\Tools;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;

final class ReadToolCatalogue
{
    private $tools;

    public function __construct(ReadToolService $tools)
    {
        $this->tools = $tools;
    }

    public function register(Builder $builder): void
    {
        $this->add($builder, 'freescout_get_ticket', 'Get ticket', 'Get metadata for one accessible FreeScout ticket.', [$this->tools, 'getTicket'], $this->schema([
            'ticket_id' => $this->integer('FreeScout ticket ID.'),
        ], ['ticket_id']));

        $this->add($builder, 'freescout_get_ticket_context', 'Get ticket context', 'Get one accessible ticket and its newest published threads.', [$this->tools, 'getTicketContext'], $this->schema([
            'ticket_id' => $this->integer('FreeScout ticket ID.'),
            'limit' => $this->limit(),
        ], ['ticket_id']));

        $this->add($builder, 'freescout_get_ticket_threads', 'Get ticket threads', 'Page through published threads for one accessible ticket, newest first.', [$this->tools, 'getTicketThreads'], $this->schema([
            'ticket_id' => $this->integer('FreeScout ticket ID.'),
            'limit' => $this->limit(),
            'cursor' => $this->cursor(),
        ], ['ticket_id']));

        $this->add($builder, 'freescout_search_tickets', 'Search tickets', 'Search only tickets visible to the authenticated FreeScout user.', [$this->tools, 'searchTickets'], $this->schema([
            'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Optional text, email, subject, body, or ticket number query.'],
            'mailbox_id' => $this->integer('Optional mailbox filter.'),
            'customer_id' => $this->integer('Optional customer filter.'),
            'assignee_id' => $this->integer('Optional assignee filter.'),
            'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'closed', 'spam']],
            'limit' => $this->limit(),
            'cursor' => $this->cursor(),
        ]));

        $this->add($builder, 'freescout_get_mailboxes', 'List mailboxes', 'List mailboxes visible to the authenticated FreeScout user.', [$this->tools, 'getMailboxes'], $this->schema([
            'limit' => $this->limit(),
            'cursor' => $this->cursor(),
        ]));

        $this->add($builder, 'freescout_search_customers', 'Search customers', 'Search customers linked to tickets visible to the authenticated user.', [$this->tools, 'searchCustomers'], $this->schema([
            'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
            'limit' => $this->limit(),
            'cursor' => $this->cursor(),
        ], ['query']));

        $this->add($builder, 'freescout_search_users', 'Search users', 'Search active FreeScout users permitted by the existing user-view policy.', [$this->tools, 'searchUsers'], $this->schema([
            'query' => ['type' => 'string', 'maxLength' => 200],
            'limit' => $this->limit(),
            'cursor' => $this->cursor(),
        ]));
    }

    /** @param callable $handler @param array<string, mixed> $inputSchema */
    private function add(Builder $builder, string $name, string $title, string $description, callable $handler, array $inputSchema): void
    {
        $builder->add(
            new Tool(
                $name,
                $title,
                $inputSchema,
                $description,
                new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
                outputSchema: ['type' => 'object', 'additionalProperties' => true]
            ),
            new CallbackToolHandler($handler)
        );
    }

    /** @param array<string, mixed> $properties @param string[] $required @return array<string, mixed> */
    private function schema(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /** @return array<string, mixed> */
    private function integer(string $description): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'description' => $description];
    }

    /** @return array<string, mixed> */
    private function limit(): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25];
    }

    /** @return array<string, mixed> */
    private function cursor(): array
    {
        return ['type' => 'string', 'maxLength' => 128, 'description' => 'Opaque cursor returned by the previous page.'];
    }
}
