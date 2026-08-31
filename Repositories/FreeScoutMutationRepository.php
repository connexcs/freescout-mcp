<?php

namespace Modules\McpServer\Repositories;

use App\Conversation;
use App\Folder;
use App\Thread;
use Mcp\Exception\ToolCallException;
use Modules\McpServer\Contracts\MutationRepository;
use Modules\McpServer\Security\McpRequestContext;

final class FreeScoutMutationRepository implements MutationRepository
{
    private $context;

    public function __construct(McpRequestContext $context)
    {
        $this->context = $context;
    }

    public function addNote(int $ticketId, string $body): array
    {
        $conversation = $this->authorizedConversation($ticketId);
        $before = (int) Thread::where('conversation_id', $conversation->id)->max('id');
        $conversation->createUserThread($this->user(), $this->plainTextHtml($body), ['type' => Thread::TYPE_NOTE]);
        $thread = Thread::where('conversation_id', $conversation->id)
            ->where('id', '>', $before)
            ->where('type', Thread::TYPE_NOTE)
            ->where('created_by_user_id', $this->user()->id)
            ->where('body', $this->plainTextHtml($body))
            ->orderBy('id', 'desc')
            ->first();
        if (null === $thread) {
            throw new \RuntimeException('FreeScout did not create the note thread.');
        }

        return ['ticket_id' => (int) $conversation->id, 'thread_id' => (int) $thread->id, 'created' => true];
    }

    public function updateTicket(int $ticketId, ?string $status, ?int $assigneeId): array
    {
        $conversation = $this->authorizedConversation($ticketId);
        $changed = [];
        $statusId = null;

        if (null !== $status) {
            $statusId = array_search($status, Conversation::$statuses, true);
            if (false === $statusId) {
                throw new ToolCallException('Invalid status.');
            }
            if ((int) $conversation->status === (int) $statusId) {
                throw new ToolCallException('Status already set.');
            }
        }

        if (null !== $assigneeId) {
            $normalized = -1 === $assigneeId ? null : $assigneeId;
            $currentAssignee = null === $conversation->user_id ? null : (int) $conversation->user_id;
            if ($currentAssignee === $normalized) {
                throw new ToolCallException('Assignee already set.');
            }
            if (-1 !== $assigneeId && !$conversation->mailbox->userHasAccess($assigneeId)) {
                throw new ToolCallException('Assignee is not available for this mailbox.');
            }
        }

        if (null !== $statusId) {
            $conversation->changeStatus((int) $statusId, $this->user());
            $changed['status'] = $status;
            $conversation->refresh();
        }
        if (null !== $assigneeId) {
            $conversation->changeUser($assigneeId, $this->user());
            $changed['assignee_id'] = -1 === $assigneeId ? null : $assigneeId;
            $conversation->refresh();
        }

        return [
            'ticket_id' => (int) $conversation->id,
            'status' => Conversation::$statuses[(int) $conversation->status] ?? (string) $conversation->status,
            'assignee_id' => null === $conversation->user_id ? null : (int) $conversation->user_id,
            'changed' => $changed,
        ];
    }

    public function createDraftReply(int $ticketId, string $body, array $cc, array $bcc): array
    {
        $conversation = $this->authorizedConversation($ticketId);
        $user = $this->user();
        if ('' === trim((string) $conversation->customer_email)) {
            throw new ToolCallException('Ticket has no reply recipient.');
        }

        $thread = new Thread();
        $thread->conversation_id = $conversation->id;
        $thread->user_id = $user->id;
        $thread->type = Thread::TYPE_MESSAGE;
        $thread->state = Thread::STATE_DRAFT;
        $thread->source_via = Thread::PERSON_USER;
        $thread->source_type = Thread::SOURCE_TYPE_WEB;
        $thread->customer_id = $conversation->customer_id;
        $thread->created_by_user_id = $user->id;
        $thread->body = $this->plainTextHtml($body);
        $thread->setTo($conversation->customer_email);
        $thread->setCc($cc);
        $thread->setBcc($bcc);
        $thread->save();

        if (!$conversation->addToFolder(Folder::TYPE_DRAFTS)) {
            throw new \RuntimeException('FreeScout Drafts folder is unavailable.');
        }
        $conversation->mailbox->updateFoldersCounters(Folder::TYPE_DRAFTS);
        event(new \App\Events\UserCreatedThreadDraft($conversation, $thread));

        return [
            'ticket_id' => (int) $conversation->id,
            'thread_id' => (int) $thread->id,
            'state' => 'draft',
            'sent' => false,
        ];
    }

    /** @return object */
    private function authorizedConversation(int $ticketId)
    {
        $conversation = Conversation::with('mailbox')->where('id', $ticketId)->lockForUpdate()->first();
        if (null === $conversation || !$this->user()->can('update', $conversation)) {
            throw new ToolCallException('Ticket not found.');
        }

        return $conversation;
    }

    /** @return object */
    private function user()
    {
        $user = $this->context->user();
        if (null === $user) {
            throw new \LogicException('Mutation attempted without an authenticated user.');
        }

        return $user;
    }

    private function plainTextHtml(string $body): string
    {
        return nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    }
}
