<?php

namespace Modules\McpServer\Repositories;

use App\Conversation;
use App\Customer;
use App\Folder;
use App\Mailbox;
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
        $statusId = $this->statusId($status);

        if (null !== $statusId && (int) $conversation->status === $statusId) {
            throw new ToolCallException('Status already set.');
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
            $conversation->changeStatus($statusId, $this->user());
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

    public function sendReply(int $ticketId, string $body, array $cc, array $bcc, ?string $status): array
    {
        $conversation = $this->authorizedConversation($ticketId);
        $user = $this->user();
        if ('' === trim((string) $conversation->customer_email)) {
            throw new ToolCallException('Ticket has no reply recipient.');
        }

        $customer = $conversation->customer;
        if (null === $customer) {
            $customer = Customer::getByEmail($conversation->customer_email);
            if (null === $customer) {
                $customer = Customer::create($conversation->customer_email);
            }
            $conversation->customer_id = $customer->id;
            $conversation->setRelation('customer', $customer);
        }

        $statusId = $this->statusId($status) ?? Conversation::STATUS_PENDING;
        $prevStatus = (int) $conversation->status;
        $wasClosed = $conversation->isClosed();
        $statusChanged = $prevStatus !== $statusId;
        $now = date('Y-m-d H:i:s');

        $conversation->status = $statusId;
        if ($statusChanged && $conversation->isClosed()) {
            $conversation->closed_by_user_id = $user->id;
            $conversation->closed_at = $now;
        } elseif ($statusChanged && $wasClosed) {
            $conversation->closed_by_user_id = null;
            $conversation->closed_at = null;
        }
        $conversation->state = Conversation::STATE_PUBLISHED;
        $conversation->setCc($cc);
        $conversation->setBcc($bcc);
        $conversation->last_reply_at = $now;
        $conversation->last_reply_from = Conversation::PERSON_USER;
        $conversation->user_updated_at = $now;
        $conversation->setPreview($this->plainTextHtml($body));
        $conversation->updateFolder();
        $conversation->save();

        $thread = new Thread();
        $thread->conversation_id = $conversation->id;
        $thread->type = Thread::TYPE_MESSAGE;
        $thread->source_via = Thread::PERSON_USER;
        $thread->source_type = Thread::SOURCE_TYPE_WEB;
        $thread->state = Thread::STATE_PUBLISHED;
        $thread->customer_id = $customer->id;
        $thread->user_id = $conversation->user_id;
        $thread->status = $statusId;
        $thread->created_by_user_id = $user->id;
        $thread->body = $this->plainTextHtml($body);
        $thread->setTo($conversation->customer_email);
        $thread->setCc($cc);
        $thread->setBcc($bcc);
        $thread->save();

        $this->fireSendReplySaveHook($conversation, $body, $cc, $bcc, $statusId, false);
        if ($statusChanged) {
            event(new \App\Events\ConversationStatusChanged($conversation));
            \Eventy::action('conversation.status_changed', $conversation, $user, true, $prevStatus);
        }

        $conversation->mailbox->updateFoldersCounters();
        $this->scheduleCustomerVisibleDelivery($conversation, $thread, false);
        $conversation->refresh();

        return [
            'ticket_id' => (int) $conversation->id,
            'thread_id' => (int) $thread->id,
            'state' => 'published',
            'sent' => false,
            'delivery_state' => 'scheduled',
            'status' => Conversation::$statuses[(int) $conversation->status] ?? (string) $conversation->status,
        ];
    }

    public function createTicket(int $mailboxId, string $subject, ?int $customerId, ?string $customerEmail, string $body, ?int $assigneeId, ?string $status): array
    {
        $mailbox = Mailbox::find($mailboxId);
        if (null === $mailbox || !$this->user()->can('view', $mailbox)) {
            throw new ToolCallException('Mailbox not found.');
        }

        $customer = null;
        if (null !== $customerId) {
            $customer = $this->authorizedCustomer($customerId);
            if (null === $customer) {
                throw new ToolCallException('Customer not found.');
            }
            if (null !== $customerEmail && !in_array(mb_strtolower($customerEmail), array_map('mb_strtolower', $customer->emails->pluck('email')->all()), true)) {
                throw new ToolCallException('Customer email does not belong to customer.');
            }
            $customerEmail = $customerEmail ?: $customer->getMainEmail();
        } else {
            if (null === $customerEmail) {
                throw new ToolCallException('Provide customer_id or customer_email.');
            }
            $customer = Customer::getByEmail($customerEmail);
            if (null === $customer) {
                $customer = Customer::create($customerEmail);
            } else {
                $customer = $this->authorizedCustomer((int) $customer->id);
                if (null === $customer) {
                    throw new ToolCallException('Customer not found.');
                }
            }
        }
        if (!$customer || !$customerEmail) {
            throw new ToolCallException('Customer not found.');
        }

        if (null !== $assigneeId && -1 !== $assigneeId && !$mailbox->userHasAccess($assigneeId)) {
            throw new ToolCallException('Assignee is not available for this mailbox.');
        }

        $statusId = $this->statusId($status) ?? Conversation::STATUS_PENDING;
        $now = date('Y-m-d H:i:s');
        $conversation = new Conversation();
        $conversation->type = Conversation::TYPE_EMAIL;
        $conversation->subject = $subject;
        $conversation->mailbox_id = $mailbox->id;
        $conversation->customer_id = $customer->id;
        $conversation->customer_email = $customerEmail;
        $conversation->user_id = (null === $assigneeId || -1 === $assigneeId) ? null : $assigneeId;
        $conversation->status = $statusId;
        if (Conversation::STATUS_CLOSED === $statusId) {
            $conversation->closed_by_user_id = $this->user()->id;
            $conversation->closed_at = $now;
        }
        $conversation->state = Conversation::STATE_PUBLISHED;
        $conversation->source_via = Conversation::PERSON_USER;
        $conversation->source_type = Conversation::SOURCE_TYPE_WEB;
        $conversation->created_by_user_id = $this->user()->id;
        $conversation->last_reply_at = $now;
        $conversation->last_reply_from = Conversation::PERSON_USER;
        $conversation->user_updated_at = $now;
        $conversation->setPreview($this->plainTextHtml($body));
        $conversation->updateFolder();
        $conversation->save();

        $thread = new Thread();
        $thread->conversation_id = $conversation->id;
        $thread->type = Thread::TYPE_MESSAGE;
        $thread->source_via = Thread::PERSON_USER;
        $thread->source_type = Thread::SOURCE_TYPE_WEB;
        $thread->state = Thread::STATE_PUBLISHED;
        $thread->first = true;
        $thread->customer_id = $customer->id;
        $thread->user_id = $conversation->user_id;
        $thread->status = $statusId;
        $thread->created_by_user_id = $this->user()->id;
        $thread->body = $this->plainTextHtml($body);
        $thread->setTo($customerEmail);
        $thread->save();

        $this->fireSendReplySaveHook($conversation, $body, [], [], $statusId, true);
        $conversation->mailbox->updateFoldersCounters();
        $this->scheduleCustomerVisibleDelivery($conversation, $thread, true);
        $conversation->refresh();

        return [
            'ticket_id' => (int) $conversation->id,
            'number' => (int) $conversation->number,
            'thread_id' => (int) $thread->id,
            'created' => true,
            'sent' => false,
            'delivery_state' => 'scheduled',
            'status' => Conversation::$statuses[(int) $conversation->status] ?? (string) $conversation->status,
        ];
    }

    public function tagsAvailable(): bool
    {
        return \Schema::hasTable('tags') && \Schema::hasTable('conversation_tag')
            && \Schema::hasColumn('tags', 'id') && \Schema::hasColumn('tags', 'name')
            && \Schema::hasColumn('conversation_tag', 'conversation_id') && \Schema::hasColumn('conversation_tag', 'tag_id');
    }

    public function setTicketTags(int $ticketId, array $tags): array
    {
        $conversation = $this->authorizedConversation($ticketId);
        if (!$this->tagsAvailable()) {
            throw new ToolCallException('Tags module is unavailable.');
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $name = trim($tag);
            if ('' !== $name) {
                $normalized[mb_strtolower($name)] = $name;
            }
        }
        $normalized = array_values($normalized);

        $tagIds = [];
        foreach ($normalized as $name) {
            $existing = \DB::table('tags')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if (!$existing || !$this->tagVisibleToUser((int) $existing->id)) {
                throw new ToolCallException('Unknown tag: '.$name.'.');
            }
            $tagIds[] = (int) $existing->id;
        }

        $current = \DB::table('conversation_tag')->where('conversation_id', $conversation->id)->pluck('tag_id')->map(function ($id) { return (int) $id; })->all();
        $add = array_values(array_diff($tagIds, $current));
        $remove = array_values(array_diff($current, $tagIds));

        foreach ($remove as $tagId) {
            \DB::table('conversation_tag')->where('conversation_id', $conversation->id)->where('tag_id', $tagId)->delete();
            if (\Schema::hasColumn('tags', 'counter')) {
                \DB::table('tags')->where('id', $tagId)->where('counter', '>', 0)->decrement('counter');
            }
        }
        foreach ($add as $tagId) {
            \DB::table('conversation_tag')->insert(['conversation_id' => $conversation->id, 'tag_id' => $tagId]);
            if (\Schema::hasColumn('tags', 'counter')) {
                \DB::table('tags')->where('id', $tagId)->increment('counter');
            }
        }

        \Eventy::action('conversation.tags_changed', $conversation, $tagIds, $current, $this->user());

        return [
            'ticket_id' => (int) $conversation->id,
            'tags' => \DB::table('tags')->whereIn('id', $tagIds)->orderBy('name')->get(['id', 'name'])->map(function ($tag) {
                return ['id' => (int) $tag->id, 'name' => (string) $tag->name];
            })->all(),
        ];
    }

    private function scheduleCustomerVisibleDelivery($conversation, $thread, bool $created): void
    {
        if ($created) {
            event(new \App\Events\UserCreatedConversation($conversation, $thread));
            \Eventy::action('conversation.created_by_user_can_undo', $conversation, $thread);
            \Helper::backgroundAction('conversation.created_by_user', [$conversation, $thread], now()->addSeconds(Conversation::UNDO_TIMOUT));
            return;
        }

        event(new \App\Events\UserReplied($conversation, $thread));
        \Eventy::action('conversation.user_replied_can_undo', $conversation, $thread);
        \Helper::backgroundAction('conversation.user_replied', [$conversation, $thread], now()->addSeconds(Conversation::UNDO_TIMOUT));
    }

    private function fireSendReplySaveHook($conversation, string $body, array $cc, array $bcc, int $statusId, bool $isCreate): void
    {
        $request = \Illuminate\Http\Request::create('/mcp', 'POST', [
            'body' => $body,
            'cc' => $cc,
            'bcc' => $bcc,
            'status' => $statusId,
            'type' => Conversation::TYPE_EMAIL,
            'is_create' => $isCreate ? 1 : 0,
        ]);
        \Eventy::action('conversation.send_reply_save', $conversation, $request);
    }

    /** @return object|null */
    private function authorizedCustomer(int $customerId)
    {
        $customer = Customer::with('emails')->find($customerId);
        if (null === $customer) {
            return null;
        }

        $query = Conversation::query()->where('customer_id', $customerId);
        $this->applyConversationAuthorization($query);
        foreach ($query->get() as $conversation) {
            if ($this->user()->can('view', $conversation)) {
                return $customer;
            }
        }

        return null;
    }

    private function tagVisibleToUser(int $tagId): bool
    {
        $query = Conversation::query()->whereExists(function ($sub) use ($tagId) {
            $sub->select(\DB::raw(1))
                ->from('conversation_tag')
                ->whereColumn('conversation_tag.conversation_id', 'conversations.id')
                ->where('conversation_tag.tag_id', $tagId);
        });
        $this->applyConversationAuthorization($query);
        foreach ($query->get() as $conversation) {
            if ($this->user()->can('view', $conversation)) {
                return true;
            }
        }

        return false;
    }

    /** @return object */
    private function authorizedConversation(int $ticketId)
    {
        $conversation = Conversation::with(['mailbox', 'customer.emails'])->where('id', $ticketId)->lockForUpdate()->first();
        if (null === $conversation || !$this->user()->can('update', $conversation)) {
            throw new ToolCallException('Ticket not found.');
        }

        return $conversation;
    }

    private function applyConversationAuthorization($query): void
    {
        $user = $this->user();
        $query->whereIn('mailbox_id', array_map('intval', $user->mailboxesIdsCanView()));
        if (!$user->isAdmin() && $user->canSeeOnlyAssignedConversations()) {
            $query->where(function ($assigned) use ($user) {
                $assigned->where('user_id', $user->id)->orWhere('created_by_user_id', $user->id);
            });
        }
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

    private function statusId(?string $status): ?int
    {
        if (null === $status) {
            return null;
        }
        $id = array_search($status, Conversation::$statuses, true);
        if (false === $id) {
            throw new ToolCallException('Invalid status.');
        }

        return (int) $id;
    }

    private function plainTextHtml(string $body): string
    {
        return nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    }
}
