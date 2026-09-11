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

    /** @param string[] $cc @param string[] $bcc @return array<string, mixed> */
    public function sendReply(int $ticketId, string $body, array $cc, array $bcc, ?string $status): array;

    /** @return array<string, mixed> */
    public function createTicket(int $mailboxId, string $subject, ?int $customerId, ?string $customerEmail, string $body, ?int $assigneeId, ?string $status): array;

    public function tagsAvailable(): bool;

    /** @param string[] $tags @return array<string, mixed> */
    public function setTicketTags(int $ticketId, array $tags): array;
}
