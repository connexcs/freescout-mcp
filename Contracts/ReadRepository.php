<?php

namespace Modules\McpServer\Contracts;

interface ReadRepository
{
    /** @return array<string, mixed>|null */
    public function ticket(int $id): ?array;

    /** @return array<string, mixed> */
    public function ticketThreads(int $id, int $limit, ?string $cursor): array;

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function searchTickets(string $query, array $filters, int $limit, ?string $cursor): array;

    /** @return array<string, mixed> */
    public function mailboxes(int $limit, ?string $cursor): array;

    /** @return array<string, mixed> */
    public function customers(string $query, int $limit, ?string $cursor): array;

    /** @return array<string, mixed> */
    public function users(string $query, int $limit, ?string $cursor): array;

    public function tagsAvailable(): bool;

    /** @return array<string, mixed> */
    public function tags(string $query, int $limit, ?string $cursor): array;

    /** @return array<int, array<string, mixed>> */
    public function ticketTags(int $ticketId): array;
}
