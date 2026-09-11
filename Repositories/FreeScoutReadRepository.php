<?php

namespace Modules\McpServer\Repositories;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcp\Exception\ToolCallException;
use Modules\McpServer\Contracts\ReadRepository;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Support\PageCursor;

final class FreeScoutReadRepository implements ReadRepository
{
    private $context;

    public function __construct(McpRequestContext $context) { $this->context = $context; }

    public function ticket(int $id): ?array
    {
        $conversation = $this->conversationQuery()->with(['mailbox', 'customer.emails', 'user'])->where('conversations.id', $id)->first();
        if (null === $conversation || !$this->user()->can('view', $conversation)) { return null; }
        return $this->serializeTicket($conversation, $this->tagsAvailable() ? $this->tagsForTickets([(int) $conversation->id])[(int) $conversation->id] ?? [] : []);
    }

    public function ticketThreads(int $id, int $limit, ?string $cursor): array
    {
        $query = Thread::query()->with(['created_by_user', 'created_by_customer', 'attachments'])->where('conversation_id', $id)->where('state', Thread::STATE_PUBLISHED)->orderBy('id', 'desc');
        $this->applyCursor($query, $cursor);
        $rows = $query->limit($limit + 1)->get(); $hasMore = $rows->count() > $limit; $rows = $rows->take($limit);
        return ['items' => $rows->map(function ($thread) { return $this->serializeThread($thread); })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    public function searchTickets(string $query, array $filters, int $limit, ?string $cursor): array
    {
        $builder = $this->conversationQuery()->with(['mailbox', 'customer.emails', 'user']);
        if ('' !== $query) {
            $like = '%'.mb_strtolower($query).'%'; $operator = \Helper::isPgSql() ? 'ilike' : 'like';
            $builder->where(function ($where) use ($query, $like, $operator) {
                $where->where('subject', $operator, $like)->orWhere('customer_email', $operator, $like)->orWhere('preview', $operator, $like)
                    ->orWhereHas('customer', function ($customers) use ($like, $operator) { $customers->where('first_name', $operator, $like)->orWhere('last_name', $operator, $like)->orWhere('company', $operator, $like)->orWhereHas('emails', function ($emails) use ($like, $operator) { $emails->where('email', $operator, $like); }); })
                    ->orWhereHas('threads', function ($threads) use ($like, $operator) { $threads->where('state', Thread::STATE_PUBLISHED)->where('body', $operator, $like); });
                if (ctype_digit($query)) { $where->orWhere('conversations.'.Conversation::numberFieldName(), (int) $query); }
            });
        }
        if (isset($filters['mailbox_id'])) { $builder->where('mailbox_id', $filters['mailbox_id']); }
        if (isset($filters['customer_id'])) { $builder->where('customer_id', $filters['customer_id']); }
        if (isset($filters['assignee_id'])) { $builder->where('user_id', $filters['assignee_id']); }
        if (isset($filters['status'])) {
            $status = array_search($filters['status'], Conversation::$statuses, true);
            if (false === $status) { throw new ToolCallException('Invalid status.'); }
            $builder->where('status', $status);
        }
        if (isset($filters['tag'])) {
            if (!$this->tagsAvailable()) { throw new ToolCallException('Tags module is unavailable.'); }
            $tag = mb_strtolower($filters['tag']);
            $builder->whereExists(function ($sub) use ($tag) {
                $sub->select(DB::raw(1))->from('conversation_tag')->join('tags', 'tags.id', '=', 'conversation_tag.tag_id')->whereColumn('conversation_tag.conversation_id', 'conversations.id')->whereRaw('LOWER(tags.name) = ?', [$tag]);
            });
        }
        $this->applyCursor($builder, $cursor);
        $rows = $builder->orderBy('conversations.id', 'desc')->limit(($limit + 1) * 5)->get()->filter(function ($conversation) { return $this->user()->can('view', $conversation); })->values()->take($limit + 1);
        $hasMore = $rows->count() > $limit; $rows = $rows->take($limit);
        $tagMap = $this->tagsAvailable() ? $this->tagsForTickets($rows->pluck('id')->map(function ($id) { return (int) $id; })->all()) : [];
        return ['items' => $rows->map(function ($conversation) use ($tagMap) { return $this->serializeTicket($conversation, $tagMap[(int) $conversation->id] ?? []); })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    public function mailboxes(int $limit, ?string $cursor): array
    {
        $builder = Mailbox::query()->whereIn('id', $this->mailboxIds())->orderBy('id', 'desc'); $this->applyCursor($builder, $cursor);
        $rows = $builder->limit($limit + 1)->get(); $hasMore = $rows->count() > $limit; $rows = $rows->take($limit);
        return ['items' => $rows->map(function ($mailbox) { return ['id' => (int) $mailbox->id, 'name' => (string) $mailbox->name, 'email' => (string) $mailbox->email]; })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    public function customers(string $query, int $limit, ?string $cursor): array
    {
        $like = '%'.mb_strtolower($query).'%'; $operator = \Helper::isPgSql() ? 'ilike' : 'like';
        $builder = Customer::query()->with('emails')->whereHas('conversations', function ($conversations) { $this->applyConversationAuthorization($conversations); })->where(function ($customers) use ($like, $operator) {
            $customers->where('first_name', $operator, $like)->orWhere('last_name', $operator, $like)->orWhere('company', $operator, $like)->orWhereHas('emails', function ($emails) use ($like, $operator) { $emails->where('email', $operator, $like); });
        })->orderBy('id', 'desc');
        $this->applyCursor($builder, $cursor); $rows = $builder->limit($limit + 1)->get(); $hasMore = $rows->count() > $limit; $rows = $rows->take($limit);
        return ['items' => $rows->map(function ($customer) { return ['id' => (int) $customer->id, 'name' => trim((string) $customer->getFullName()), 'emails' => $customer->emails->pluck('email')->values()->all(), 'company' => $customer->company ?: null]; })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    public function users(string $query, int $limit, ?string $cursor): array
    {
        $actor = $this->user(); $builder = User::query()->where('status', User::STATUS_ACTIVE)->orderBy('id', 'desc');
        if (!$actor->isAdmin()) { $builder->where('id', $actor->id); }
        if ('' !== $query) { $like = '%'.mb_strtolower($query).'%'; $operator = \Helper::isPgSql() ? 'ilike' : 'like'; $builder->where(function ($users) use ($like, $operator) { $users->where('first_name', $operator, $like)->orWhere('last_name', $operator, $like)->orWhere('email', $operator, $like); }); }
        $this->applyCursor($builder, $cursor); $rows = $builder->limit($limit + 1)->get(); $hasMore = $rows->count() > $limit; $rows = $rows->take($limit);
        return ['items' => $rows->map(function ($user) { return ['id' => (int) $user->id, 'name' => trim((string) $user->getFullName()), 'email' => (string) $user->email]; })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    public function tagsAvailable(): bool
    {
        return Schema::hasTable('tags') && Schema::hasTable('conversation_tag') && Schema::hasColumn('tags', 'id') && Schema::hasColumn('tags', 'name') && Schema::hasColumn('conversation_tag', 'conversation_id') && Schema::hasColumn('conversation_tag', 'tag_id');
    }

    public function ticketTags(int $ticketId): array
    {
        if (!$this->tagsAvailable()) { throw new ToolCallException('Tags module is unavailable.'); }
        $conversation = $this->conversationQuery()->where('conversations.id', $ticketId)->first();
        if (null === $conversation || !$this->user()->can('view', $conversation)) { throw new ToolCallException('Ticket not found.'); }
        $map = $this->tagsForTickets([$ticketId]);
        return $map[$ticketId] ?? [];
    }

    public function tags(string $query, int $limit, ?string $cursor): array
    {
        if (!$this->tagsAvailable()) { throw new ToolCallException('Tags module is unavailable.'); }
        try { $scanBefore = PageCursor::decode($cursor); } catch (\InvalidArgumentException $exception) { throw new ToolCallException($exception->getMessage()); }

        $visible = collect();
        $chunkSize = max(100, ($limit + 1) * 2);
        do {
            $builder = $this->tagSelect(DB::table('tags'));
            if ('' !== $query) { $builder->whereRaw('LOWER(tags.name) LIKE ?', ['%'.mb_strtolower($query).'%']); }
            if (null !== $scanBefore) { $builder->where('tags.id', '<', $scanBefore); }
            $candidates = $builder->orderBy('tags.id', 'desc')->limit($chunkSize)->get();
            if ($candidates->isEmpty()) { break; }

            $scanBefore = (int) $candidates->last()->id;
            $visibleIds = $this->visibleTagIds($candidates->pluck('id')->map(function ($id) { return (int) $id; })->all());
            foreach ($candidates as $tag) {
                if (isset($visibleIds[(int) $tag->id])) {
                    $visible->push($tag);
                    if ($visible->count() >= $limit + 1) { break 2; }
                }
            }
        } while ($candidates->count() === $chunkSize);

        $hasMore = $visible->count() > $limit;
        $rows = $visible->take($limit);
        return ['items' => $rows->map(function ($tag) { return $this->serializeTag($tag); })->values()->all(), 'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null];
    }

    private function tagSelect($query)
    {
        $columns = ['tags.id', 'tags.name'];
        if (Schema::hasColumn('tags', 'color')) { $columns[] = 'tags.color'; }
        return $query->select($columns);
    }

    private function serializeTag($tag): array
    {
        return ['id' => (int) $tag->id, 'name' => (string) $tag->name, 'color' => isset($tag->color) ? (int) $tag->color : null];
    }

    private function tagsForTickets(array $ticketIds): array
    {
        if (!$ticketIds || !$this->tagsAvailable()) { return []; }
        $rows = $this->tagSelect(DB::table('tags')->join('conversation_tag', 'conversation_tag.tag_id', '=', 'tags.id'))
            ->addSelect('conversation_tag.conversation_id')
            ->whereIn('conversation_tag.conversation_id', $ticketIds)
            ->orderBy('tags.name')
            ->get();
        $map = [];
        foreach ($rows as $tag) {
            $ticketId = (int) $tag->conversation_id;
            if (!isset($map[$ticketId])) { $map[$ticketId] = []; }
            $map[$ticketId][] = $this->serializeTag($tag);
        }
        return $map;
    }

    private function visibleTagIds(array $tagIds): array
    {
        if (!$tagIds) { return []; }
        $conversations = $this->conversationQuery()
            ->join('conversation_tag', 'conversation_tag.conversation_id', '=', 'conversations.id')
            ->whereIn('conversation_tag.tag_id', $tagIds)
            ->get(['conversations.*', 'conversation_tag.tag_id as mcp_tag_id']);
        $visible = [];
        foreach ($conversations as $conversation) {
            if ($this->user()->can('view', $conversation)) {
                $visible[(int) $conversation->mcp_tag_id] = true;
            }
        }
        return $visible;
    }

    private function conversationQuery() { $query = Conversation::query(); $this->applyConversationAuthorization($query); return $query; }
    private function applyConversationAuthorization($query): void
    {
        $user = $this->user(); $query->whereIn('mailbox_id', $this->mailboxIds());
        if (!$user->isAdmin() && $user->canSeeOnlyAssignedConversations()) { $query->where(function ($assigned) use ($user) { $assigned->where('user_id', $user->id)->orWhere('created_by_user_id', $user->id); }); }
    }
    private function mailboxIds(): array { return array_map('intval', $this->user()->mailboxesIdsCanView()); }
    private function applyCursor($query, ?string $cursor): void
    {
        try { $id = PageCursor::decode($cursor); } catch (\InvalidArgumentException $exception) { throw new ToolCallException($exception->getMessage()); }
        if (null !== $id) { $query->where($query->getModel()->getTable().'.id', '<', $id); }
    }
    private function user() { $user = $this->context->user(); if (null === $user) { throw new \LogicException('MCP read attempted without an authenticated user.'); } return $user; }

    private function serializeTicket($conversation, array $tags = []): array
    {
        return ['id' => (int) $conversation->id, 'number' => (int) $conversation->number, 'subject' => (string) $conversation->subject, 'status' => $conversation->getStatusName(), 'type' => $conversation->getTypeName(),
            'mailbox' => $conversation->mailbox ? ['id' => (int) $conversation->mailbox->id, 'name' => (string) $conversation->mailbox->name] : null,
            'customer' => $conversation->customer ? ['id' => (int) $conversation->customer->id, 'name' => trim((string) $conversation->customer->getFullName()), 'email' => (string) $conversation->customer_email] : null,
            'assignee' => $conversation->user ? ['id' => (int) $conversation->user->id, 'name' => trim((string) $conversation->user->getFullName())] : null,
            'tags' => $tags, 'preview' => (string) $conversation->preview, 'thread_count' => (int) $conversation->threads_count, 'has_attachments' => (bool) $conversation->has_attachments,
            'created_at' => $this->date($conversation->created_at), 'updated_at' => $this->date($conversation->updated_at), 'last_reply_at' => $this->date($conversation->last_reply_at)];
    }

    private function serializeThread($thread): array
    {
        $creator = null;
        if ($thread->created_by_user) { $creator = ['type' => 'user', 'id' => (int) $thread->created_by_user->id, 'name' => trim((string) $thread->created_by_user->getFullName())]; }
        elseif ($thread->created_by_customer) { $creator = ['type' => 'customer', 'id' => (int) $thread->created_by_customer->id, 'name' => trim((string) $thread->created_by_customer->getFullName())]; }
        $body = mb_substr((string) $thread->getBodyAsText(['width' => 0]), 0, 50000);
        return ['id' => (int) $thread->id, 'type' => Thread::$types[$thread->type] ?? 'unknown', 'body' => $body, 'from' => $thread->from ?: null, 'to' => $this->addressList($thread->to), 'cc' => $this->addressList($thread->cc), 'creator' => $creator,
            'attachments' => $thread->attachments->map(function ($attachment) { return ['id' => (int) $attachment->id, 'name' => (string) $attachment->file_name, 'mime_type' => $attachment->mime_type ?: null, 'size' => isset($attachment->size) ? (int) $attachment->size : null]; })->values()->all(), 'created_at' => $this->date($thread->created_at)];
    }
    private function addressList($value): array { if (is_array($value)) { return array_values($value); } $decoded = json_decode((string) $value, true); return is_array($decoded) ? array_values($decoded) : []; }
    private function date($value): ?string { return null === $value ? null : $value->toIso8601String(); }
}
