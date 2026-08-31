<?php

namespace Modules\McpServer\Tools;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Contracts\ReadRepository;
use Modules\McpServer\Security\PermissionDenied;

final class ReadToolService
{
    private $repository;

    public function __construct(ReadRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function getTicket(array $arguments): array
    {
        $ticket = $this->repository->ticket($this->positiveId($arguments, 'ticket_id'));
        if (null === $ticket) {
            throw new ToolCallException('Ticket not found.');
        }

        return ['ticket' => $ticket];
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function getTicketContext(array $arguments): array
    {
        $ticket = $this->repository->ticket($this->positiveId($arguments, 'ticket_id'));
        if (null === $ticket) {
            throw new ToolCallException('Ticket not found.');
        }

        $threads = $this->repository->ticketThreads((int) $ticket['id'], $this->limit($arguments, 20), null);

        return ['ticket' => $ticket, 'threads' => $threads['items'], 'next_cursor' => $threads['next_cursor']];
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function getTicketThreads(array $arguments): array
    {
        $id = $this->positiveId($arguments, 'ticket_id');
        if (null === $this->repository->ticket($id)) {
            throw new ToolCallException('Ticket not found.');
        }

        return $this->repository->ticketThreads($id, $this->limit($arguments), $this->cursor($arguments));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function searchTickets(array $arguments): array
    {
        $query = $this->shortString($arguments, 'query', 200, false);
        $filters = [];
        foreach (['mailbox_id', 'customer_id', 'assignee_id'] as $name) {
            if (isset($arguments[$name])) {
                $filters[$name] = $this->positiveId($arguments, $name);
            }
        }
        if (isset($arguments['status'])) {
            $status = $this->shortString($arguments, 'status', 20);
            if (!in_array($status, ['active', 'pending', 'closed', 'spam'], true)) {
                throw new ToolCallException('Invalid status.');
            }
            $filters['status'] = $status;
        }

        return $this->repository->searchTickets($query, $filters, $this->limit($arguments), $this->cursor($arguments));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function getMailboxes(array $arguments): array
    {
        return $this->repository->mailboxes($this->limit($arguments), $this->cursor($arguments));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function searchCustomers(array $arguments): array
    {
        return $this->repository->customers(
            $this->shortString($arguments, 'query', 200),
            $this->limit($arguments),
            $this->cursor($arguments)
        );
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function searchUsers(array $arguments): array
    {
        return $this->repository->users(
            $this->shortString($arguments, 'query', 200, false),
            $this->limit($arguments),
            $this->cursor($arguments)
        );
    }

    /** @param array<string, mixed> $arguments */
    private function positiveId(array $arguments, string $name): int
    {
        $value = filter_var($arguments[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $value) {
            throw new ToolCallException($name.' must be a positive integer.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $arguments */
    private function limit(array $arguments, int $default = 25): int
    {
        if (!isset($arguments['limit'])) {
            return $default;
        }
        $value = filter_var($arguments['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (false === $value) {
            throw new ToolCallException('limit must be between 1 and 100.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $arguments */
    private function cursor(array $arguments): ?string
    {
        if (!isset($arguments['cursor'])) {
            return null;
        }
        if (!is_string($arguments['cursor']) || strlen($arguments['cursor']) > 128) {
            throw new ToolCallException('Invalid pagination cursor.');
        }

        return $arguments['cursor'];
    }

    /** @param array<string, mixed> $arguments */
    private function shortString(array $arguments, string $name, int $max, bool $required = true): string
    {
        $value = $arguments[$name] ?? '';
        if (!is_string($value) || ($required && '' === trim($value)) || mb_strlen($value) > $max) {
            throw new ToolCallException($name.' must be '.($required ? 'a non-empty ' : 'a ').'string no longer than '.$max.' characters.');
        }

        return trim($value);
    }
}
