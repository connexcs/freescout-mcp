<?php

namespace Modules\McpServer\Tests\Support;

use Modules\McpServer\Contracts\ReadRepository;

final class FakeReadRepository implements ReadRepository
{
    /** @var array<int, array<string, mixed>> */
    public $tickets = [];

    public function ticket(int $id): ?array
    {
        return $this->tickets[$id] ?? null;
    }

    public function ticketThreads(int $id, int $limit, ?string $cursor): array
    {
        return ['items' => [['id' => 10, 'body' => 'Hello']], 'next_cursor' => null];
    }

    public function searchTickets(string $query, array $filters, int $limit, ?string $cursor): array
    {
        return ['items' => array_values($this->tickets), 'next_cursor' => null];
    }

    public function mailboxes(int $limit, ?string $cursor): array
    {
        return ['items' => [['id' => 1, 'name' => 'Support']], 'next_cursor' => null];
    }

    public function customers(string $query, int $limit, ?string $cursor): array
    {
        return ['items' => [], 'next_cursor' => null];
    }

    public function users(string $query, int $limit, ?string $cursor): array
    {
        return ['items' => [], 'next_cursor' => null];
    }

    public function tagsAvailable(): bool
    {
        return true;
    }

    public function tags(string $query, int $limit, ?string $cursor): array
    {
        return ['items' => [['id' => 1, 'name' => 'priority']], 'next_cursor' => null];
    }

    public function ticketTags(int $ticketId): array
    {
        return [['id' => 1, 'name' => 'priority']];
    }
}
