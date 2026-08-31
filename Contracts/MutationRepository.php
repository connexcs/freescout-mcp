<?php

namespace Modules\McpServer\Contracts;

interface MutationRepository
{
    /** @return array<string, mixed> */
    public function addNote(int $ticketId, string $body): array;

    /** @return array<string, mixed> */
    public function updateTicket(int $ticketId, ?string $status, ?int $assigneeId): array;

    /** @param string[] $cc @param string[] $bcc @return array<string, mixed> */
    public function createDraftReply(int $ticketId, string $body, array $cc, array $bcc): array;
}
