<?php

namespace Modules\McpServer\Repositories;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;
use App\User;
use Mcp\Exception\ToolCallException;
use Modules\McpServer\Contracts\ReadRepository;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Support\PageCursor;

final class FreeScoutReadRepository implements ReadRepository
{
    private $context;

    public function __construct(McpRequestContext $context)
    {
        $this->context = $context;
    }

    public function ticket(int $id): ?array
    {
        $conversation = $this->conversationQuery()
            ->with(['mailbox', 'customer.emails', 'user'])
            ->where('conversations.id', $id)
            ->first();

        if (null === $conversation || !$this->user()->can('view', $conversation)) {
            return null;
        }

        return $this->serializeTicket($conversation);
    }

    public function ticketThreads(int $id, int $limit, ?string $cursor): array
    {
        $query = Thread::query()
            ->with(['created_by_user', 'created_by_customer', 'attachments'])
            ->where('conversation_id', $id)
            ->where('state', Thread::STATE_PUBLISHED)
            ->orderBy('id', 'desc');
        $this->applyCursor($query, $cursor);
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(function ($thread) {
                return $this->serializeThread($thread);
            })->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    public function searchTickets(string $query, array $filters, int $limit, ?string $cursor): array
    {
        $builder = $this->conversationQuery()->with(['mailbox', 'customer.emails', 'user']);

        if ('' !== $query) {
            $like = '%'.mb_strtolower($query).'%';
            $operator = \Helper::isPgSql() ? 'ilike' : 'like';
            $builder->where(function ($where) use ($query, $like, $operator) {
                $where->where('subject', $operator, $like)
                    ->orWhere('customer_email', $operator, $like)
                    ->orWhere('preview', $operator, $like)
                    ->orWhereHas('customer', function ($customers) use ($like, $operator) {
                        $customers->where('first_name', $operator, $like)
                            ->orWhere('last_name', $operator, $like)
                            ->orWhere('company', $operator, $like)
                            ->orWhereHas('emails', function ($emails) use ($like, $operator) {
                                $emails->where('email', $operator, $like);
                            });
                    })
                    ->orWhereHas('threads', function ($threads) use ($like, $operator) {
                        $threads->where('state', Thread::STATE_PUBLISHED)->where('body', $operator, $like);
                    });

                if (ctype_digit($query)) {
                    $where->orWhere('conversations.'.Conversation::numberFieldName(), (int) $query);
                }
            });
        }

        if (isset($filters['mailbox_id'])) {
            $builder->where('mailbox_id', $filters['mailbox_id']);
        }
        if (isset($filters['customer_id'])) {
            $builder->where('customer_id', $filters['customer_id']);
        }
        if (isset($filters['assignee_id'])) {
            $builder->where('user_id', $filters['assignee_id']);
        }
        if (isset($filters['status'])) {
            $status = array_search($filters['status'], Conversation::$statuses, true);
            if (false === $status) {
                throw new ToolCallException('Invalid status.');
            }
            $builder->where('status', $status);
        }

        $this->applyCursor($builder, $cursor);
        // Re-check FreeScout's policy so extensions cannot widen the query result.
        // Fetching a bounded overage lets denied records disappear without exposing
        // them through counts or an otherwise unexplained next cursor.
        $rows = $builder->orderBy('conversations.id', 'desc')->limit(($limit + 1) * 5)->get()
            ->filter(function ($conversation) {
                return $this->user()->can('view', $conversation);
            })->values()->take($limit + 1);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(function ($conversation) {
                return $this->serializeTicket($conversation);
            })->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    public function mailboxes(int $limit, ?string $cursor): array
    {
        $ids = $this->mailboxIds();
        $builder = Mailbox::query()->whereIn('id', $ids)->orderBy('id', 'desc');
        $this->applyCursor($builder, $cursor);
        $rows = $builder->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(function ($mailbox) {
                return ['id' => (int) $mailbox->id, 'name' => (string) $mailbox->name, 'email' => (string) $mailbox->email];
            })->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    public function customers(string $query, int $limit, ?string $cursor): array
    {
        $like = '%'.mb_strtolower($query).'%';
        $operator = \Helper::isPgSql() ? 'ilike' : 'like';
        $builder = Customer::query()
            ->with('emails')
            ->whereHas('conversations', function ($conversations) {
                $this->applyConversationAuthorization($conversations);
            })
            ->where(function ($customers) use ($like, $operator) {
                $customers->where('first_name', $operator, $like)
                    ->orWhere('last_name', $operator, $like)
                    ->orWhere('company', $operator, $like)
                    ->orWhereHas('emails', function ($emails) use ($like, $operator) {
                        $emails->where('email', $operator, $like);
                    });
            })
            ->orderBy('id', 'desc');
        $this->applyCursor($builder, $cursor);
        $rows = $builder->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(function ($customer) {
                return [
                    'id' => (int) $customer->id,
                    'name' => trim((string) $customer->getFullName()),
                    'emails' => $customer->emails->pluck('email')->values()->all(),
                    'company' => $customer->company ?: null,
                ];
            })->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    public function users(string $query, int $limit, ?string $cursor): array
    {
        $actor = $this->user();
        $builder = User::query()->where('status', User::STATUS_ACTIVE)->orderBy('id', 'desc');
        if (!$actor->isAdmin()) {
            $builder->where('id', $actor->id);
        }
        if ('' !== $query) {
            $like = '%'.mb_strtolower($query).'%';
            $operator = \Helper::isPgSql() ? 'ilike' : 'like';
            $builder->where(function ($users) use ($like, $operator) {
                $users->where('first_name', $operator, $like)
                    ->orWhere('last_name', $operator, $like)
                    ->orWhere('email', $operator, $like);
            });
        }
        $this->applyCursor($builder, $cursor);
        $rows = $builder->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(function ($user) {
                return ['id' => (int) $user->id, 'name' => trim((string) $user->getFullName()), 'email' => (string) $user->email];
            })->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    private function conversationQuery()
    {
        $query = Conversation::query();
        $this->applyConversationAuthorization($query);

        return $query;
    }

    private function applyConversationAuthorization($query): void
    {
        $user = $this->user();
        $query->whereIn('mailbox_id', $this->mailboxIds());
        if (!$user->isAdmin() && $user->canSeeOnlyAssignedConversations()) {
            $query->where(function ($assigned) use ($user) {
                $assigned->where('user_id', $user->id)->orWhere('created_by_user_id', $user->id);
            });
        }
    }

    /** @return int[] */
    private function mailboxIds(): array
    {
        return array_map('intval', $this->user()->mailboxesIdsCanView());
    }

    private function applyCursor($query, ?string $cursor): void
    {
        try {
            $id = PageCursor::decode($cursor);
        } catch (\InvalidArgumentException $exception) {
            throw new ToolCallException($exception->getMessage());
        }
        if (null !== $id) {
            $query->where($query->getModel()->getTable().'.id', '<', $id);
        }
    }

    /** @return object */
    private function user()
    {
        $user = $this->context->user();
        if (null === $user) {
            throw new \LogicException('MCP read attempted without an authenticated user.');
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function serializeTicket($conversation): array
    {
        return [
            'id' => (int) $conversation->id,
            'number' => (int) $conversation->number,
            'subject' => (string) $conversation->subject,
            'status' => $conversation->getStatusName(),
            'type' => $conversation->getTypeName(),
            'mailbox' => $conversation->mailbox ? ['id' => (int) $conversation->mailbox->id, 'name' => (string) $conversation->mailbox->name] : null,
            'customer' => $conversation->customer ? [
                'id' => (int) $conversation->customer->id,
                'name' => trim((string) $conversation->customer->getFullName()),
                'email' => (string) $conversation->customer_email,
            ] : null,
            'assignee' => $conversation->user ? ['id' => (int) $conversation->user->id, 'name' => trim((string) $conversation->user->getFullName())] : null,
            'preview' => (string) $conversation->preview,
            'thread_count' => (int) $conversation->threads_count,
            'has_attachments' => (bool) $conversation->has_attachments,
            'created_at' => $this->date($conversation->created_at),
            'updated_at' => $this->date($conversation->updated_at),
            'last_reply_at' => $this->date($conversation->last_reply_at),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeThread($thread): array
    {
        $creator = null;
        if ($thread->created_by_user) {
            $creator = ['type' => 'user', 'id' => (int) $thread->created_by_user->id, 'name' => trim((string) $thread->created_by_user->getFullName())];
        } elseif ($thread->created_by_customer) {
            $creator = ['type' => 'customer', 'id' => (int) $thread->created_by_customer->id, 'name' => trim((string) $thread->created_by_customer->getFullName())];
        }

        $body = mb_substr((string) $thread->getBodyAsText(['width' => 0]), 0, 50000);

        return [
            'id' => (int) $thread->id,
            'type' => Thread::$types[$thread->type] ?? 'unknown',
            'body' => $body,
            'from' => $thread->from ?: null,
            'to' => $this->addressList($thread->to),
            'cc' => $this->addressList($thread->cc),
            'creator' => $creator,
            'attachments' => $thread->attachments->map(function ($attachment) {
                return ['id' => (int) $attachment->id, 'name' => (string) $attachment->file_name, 'mime_type' => $attachment->mime_type ?: null, 'size' => isset($attachment->size) ? (int) $attachment->size : null];
            })->values()->all(),
            'created_at' => $this->date($thread->created_at),
        ];
    }

    /** @return string[] */
    private function addressList($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function date($value): ?string
    {
        return null === $value ? null : $value->toIso8601String();
    }
}
