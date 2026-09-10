<?php

namespace Modules\McpServer\Tools;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Modules\McpServer\KnowledgeBase\KnowledgeBaseToolService;

final class ReadToolCatalogue
{
    private $tools;
    private $knowledgeBase;

    public function __construct(ReadToolService $tools, ?KnowledgeBaseToolService $knowledgeBase = null) { $this->tools = $tools; $this->knowledgeBase = $knowledgeBase; }

    public function register(Builder $builder): void
    {
        $this->add($builder, 'freescout_get_ticket', 'Get ticket', 'Get metadata for one accessible FreeScout ticket.', [$this->tools, 'getTicket'], $this->schema(['ticket_id' => $this->integer('FreeScout ticket ID.')], ['ticket_id']));
        $this->add($builder, 'freescout_get_ticket_context', 'Get ticket context', 'Get one accessible ticket and its newest published threads.', [$this->tools, 'getTicketContext'], $this->schema(['ticket_id' => $this->integer('FreeScout ticket ID.'), 'limit' => $this->limit()], ['ticket_id']));
        $this->add($builder, 'freescout_get_ticket_threads', 'Get ticket threads', 'Page through published threads for one accessible ticket, newest first.', [$this->tools, 'getTicketThreads'], $this->schema(['ticket_id' => $this->integer('FreeScout ticket ID.'), 'limit' => $this->limit(), 'cursor' => $this->cursor()], ['ticket_id']));
        $searchProperties = [
            'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Optional text, email, subject, body, or ticket number query.'],
            'mailbox_id' => $this->integer('Optional mailbox filter.'), 'customer_id' => $this->integer('Optional customer filter.'), 'assignee_id' => $this->integer('Optional assignee filter.'),
            'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'closed', 'spam']], 'limit' => $this->limit(), 'cursor' => $this->cursor(),
        ];
        if ($this->tools->tagsAvailable()) { $searchProperties['tag'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'description' => 'Optional exact tag-name filter.']; }
        $this->add($builder, 'freescout_search_tickets', 'Search tickets', 'Search only tickets visible to the authenticated FreeScout user.', [$this->tools, 'searchTickets'], $this->schema($searchProperties));
        $this->add($builder, 'freescout_get_mailboxes', 'List mailboxes', 'List mailboxes visible to the authenticated FreeScout user.', [$this->tools, 'getMailboxes'], $this->schema(['limit' => $this->limit(), 'cursor' => $this->cursor()]));
        $this->add($builder, 'freescout_search_customers', 'Search customers', 'Search customers linked to tickets visible to the authenticated user.', [$this->tools, 'searchCustomers'], $this->schema(['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'limit' => $this->limit(), 'cursor' => $this->cursor()], ['query']));
        $this->add($builder, 'freescout_search_users', 'Search users', 'Search active FreeScout users permitted by the existing user-view policy.', [$this->tools, 'searchUsers'], $this->schema(['query' => ['type' => 'string', 'maxLength' => 200], 'limit' => $this->limit(), 'cursor' => $this->cursor()]));

        if ($this->tools->tagsAvailable()) {
            $this->add($builder, 'freescout_get_ticket_tags', 'Get ticket tags', 'Read all tags attached to an accessible ticket.', [$this->tools, 'getTicketTags'], $this->schema(['ticket_id' => $this->integer('FreeScout ticket ID.')], ['ticket_id']));
            $this->add($builder, 'freescout_search_tags', 'Search tags', 'Search tags linked to tickets visible to the authenticated user.', [$this->tools, 'searchTags'], $this->schema(['query' => ['type' => 'string', 'maxLength' => 191], 'limit' => $this->limit(), 'cursor' => $this->cursor()]));
        }

        if (null !== $this->knowledgeBase && $this->knowledgeBase->available()) {
            $this->add($builder, 'freescout_search_kb_articles', 'Search Knowledge Base articles', 'Search Knowledge Base articles in accessible mailboxes.', [$this->knowledgeBase, 'searchArticles'], $this->schema(['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'limit' => $this->limit(), 'cursor' => $this->cursor()], ['query']));
            $this->add($builder, 'freescout_get_kb_article', 'Get Knowledge Base article', 'Read a Knowledge Base article in an accessible mailbox.', [$this->knowledgeBase, 'getArticle'], $this->schema(['article_id' => $this->integer('Knowledge Base article ID.')], ['article_id']));
            $this->add($builder, 'freescout_search_kb_categories', 'Search Knowledge Base categories', 'Search Knowledge Base categories in accessible mailboxes.', [$this->knowledgeBase, 'searchCategories'], $this->schema(['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'limit' => $this->limit(), 'cursor' => $this->cursor()], ['query']));
            $this->add($builder, 'freescout_get_kb_category', 'Get Knowledge Base category', 'Read a Knowledge Base category in an accessible mailbox.', [$this->knowledgeBase, 'getCategory'], $this->schema(['category_id' => $this->integer('Knowledge Base category ID.')], ['category_id']));
        }
    }

    private function add(Builder $builder, string $name, string $title, string $description, callable $handler, array $inputSchema): void
    {
        $builder->add(new Tool($name, $title, $inputSchema, $description, new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: ['type' => 'object', 'additionalProperties' => true]), new CallbackToolHandler($handler));
    }
    private function schema(array $properties, array $required = []): array { return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false]; }
    private function integer(string $description): array { return ['type' => 'integer', 'minimum' => 1, 'description' => $description]; }
    private function limit(): array { return ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25]; }
    private function cursor(): array { return ['type' => 'string', 'maxLength' => 128, 'description' => 'Opaque cursor returned by the previous page.']; }
}
