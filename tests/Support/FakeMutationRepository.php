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
}
