<?php

namespace Modules\McpServer\Tests\Support;

use Modules\McpServer\Contracts\MutationRepository;

final class FakeMutationRepository implements MutationRepository
{
    public $calls = [];

    public function addNote(int $ticketId, string $body): array
    {
        $this->calls[] = ['note', $ticketId, $body];
        return ['ticket_id' => $ticketId, 'thread_id' => 10];
    }

    public function updateTicket(int $ticketId, ?string $status, ?int $assigneeId): array
    {
        $this->calls[] = ['update', $ticketId, $status, $assigneeId];
        return ['ticket_id' => $ticketId, 'status' => $status, 'assignee_id' => $assigneeId];
    }

    public function createDraftReply(int $ticketId, string $body, array $cc, array $bcc): array
    {
        $this->calls[] = ['draft', $ticketId, $body, $cc, $bcc];
        return ['ticket_id' => $ticketId, 'thread_id' => 11, 'sent' => false];
    }

    public function sendReply(int $ticketId, string $body, array $cc, array $bcc, ?string $status): array
    {
        $this->calls[] = ['send', $ticketId, $body, $cc, $bcc, $status];
        return ['ticket_id' => $ticketId, 'thread_id' => 12, 'state' => 'published', 'sent' => true, 'status' => $status ?? 'pending'];
    }

    public function createTicket(int $mailboxId, string $subject, ?int $customerId, ?string $customerEmail, string $body, ?int $assigneeId, ?string $status): array
    {
        $this->calls[] = ['create', $mailboxId, $subject, $customerId, $customerEmail, $body, $assigneeId, $status];
        return ['ticket_id' => 20, 'number' => 10020, 'thread_id' => 21, 'created' => true, 'sent' => true, 'status' => $status ?? 'pending'];
    }

    public function tagsAvailable(): bool
    {
        return true;
    }

    public function setTicketTags(int $ticketId, array $tags): array
    {
        $this->calls[] = ['tags', $ticketId, $tags];
        return ['ticket_id' => $ticketId, 'tags' => array_map(function ($name, $index) {
            return ['id' => $index + 1, 'name' => $name];
        }, $tags, array_keys($tags))];
    }
}
