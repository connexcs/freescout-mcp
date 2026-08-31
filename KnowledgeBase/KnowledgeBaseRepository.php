<?php

namespace Modules\McpServer\KnowledgeBase;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Support\PageCursor;

final class KnowledgeBaseRepository
{
    private $context;
    /** @var array<string, mixed>|null|false */
    private $schema;

    public function __construct(McpRequestContext $context)
    {
        $this->context = $context;
    }

    public function available(): bool
    {
        if (!class_exists(\App\Module::class) || !\App\Module::isActive('knowledgebase')) {
            return false;
        }

        return false !== $this->schema();
    }

    /** @return array<string, mixed>|null */
    public function article(int $id): ?array
    {
        $schema = $this->requireSchema();
        $row = $this->articleQuery($schema)->where($schema['articles'].'.id', $id)->first();

        return null === $row ? null : $this->serializeArticle($row, $schema, true);
    }

    /** @return array<string, mixed> */
    public function searchArticles(string $query, int $limit, ?string $cursor): array
    {
        $schema = $this->requireSchema();
        $table = $schema['articles'];
        $like = '%'.mb_strtolower($query).'%';
        $operator = \Helper::isPgSql() ? 'ilike' : 'like';
        $builder = $this->articleQuery($schema)->where(function ($where) use ($schema, $table, $like, $operator) {
            $where->where($table.'.'.$schema['article_title'], $operator, $like)
                ->orWhere($table.'.'.$schema['article_body'], $operator, $like);
        })->orderBy($table.'.id', 'desc');
        $this->applyCursor($builder, $table, $cursor);
        $rows = $builder->limit($limit + 1)->get();

        return $this->page($rows, $limit, function ($row) use ($schema) {
            return $this->serializeArticle($row, $schema, false);
        });
    }

    /** @return array<string, mixed>|null */
    public function category(int $id): ?array
    {
        $schema = $this->requireSchema();
        if (null === $schema['categories']) {
            return null;
        }
        $row = $this->categoryQuery($schema)->where($schema['categories'].'.id', $id)->first();

        return null === $row ? null : $this->serializeCategory($row, $schema);
    }

    /** @return array<string, mixed> */
    public function searchCategories(string $query, int $limit, ?string $cursor): array
    {
        $schema = $this->requireSchema();
        if (null === $schema['categories']) {
            return ['items' => [], 'next_cursor' => null];
        }
        $table = $schema['categories'];
        $like = '%'.mb_strtolower($query).'%';
        $operator = \Helper::isPgSql() ? 'ilike' : 'like';
        $builder = $this->categoryQuery($schema)
            ->where($table.'.'.$schema['category_title'], $operator, $like)
            ->orderBy($table.'.id', 'desc');
        $this->applyCursor($builder, $table, $cursor);
        $rows = $builder->limit($limit + 1)->get();

        return $this->page($rows, $limit, function ($row) use ($schema) {
            return $this->serializeCategory($row, $schema);
        });
    }

    private function articleQuery(array $schema)
    {
        return \DB::table($schema['articles'])->whereIn($schema['articles'].'.'.$schema['article_mailbox'], $this->mailboxIds());
    }

    private function categoryQuery(array $schema)
    {
        return \DB::table($schema['categories'])->whereIn($schema['categories'].'.'.$schema['category_mailbox'], $this->mailboxIds());
    }

    /** @return array<string, mixed>|false */
    private function schema()
    {
        if (null !== $this->schema) {
            return $this->schema;
        }

        $articles = $this->firstTable(['kb_articles', 'knowledgebase_articles', 'knowledge_base_articles']);
        if (null === $articles) {
            return $this->schema = false;
        }
        $articleColumns = \Schema::getColumnListing($articles);
        $articleTitle = $this->firstColumn($articleColumns, ['title', 'name']);
        $articleBody = $this->firstColumn($articleColumns, ['body', 'content', 'text']);
        $articleMailbox = $this->firstColumn($articleColumns, ['mailbox_id']);
        if (null === $articleTitle || null === $articleBody || null === $articleMailbox) {
            return $this->schema = false;
        }

        $categories = $this->firstTable(['kb_categories', 'knowledgebase_categories', 'knowledge_base_categories']);
        $categoryTitle = null;
        $categoryMailbox = null;
        if (null !== $categories) {
            $categoryColumns = \Schema::getColumnListing($categories);
            $categoryTitle = $this->firstColumn($categoryColumns, ['title', 'name']);
            $categoryMailbox = $this->firstColumn($categoryColumns, ['mailbox_id']);
            if (null === $categoryTitle || null === $categoryMailbox) {
                $categories = null;
            }
        }
        if (null === $categories) {
            return $this->schema = false;
        }

        return $this->schema = [
            'articles' => $articles,
            'article_columns' => $articleColumns,
            'article_title' => $articleTitle,
            'article_body' => $articleBody,
            'article_mailbox' => $articleMailbox,
            'categories' => $categories,
            'category_columns' => null === $categories ? [] : $categoryColumns,
            'category_title' => $categoryTitle,
            'category_mailbox' => $categoryMailbox,
        ];
    }

    private function requireSchema(): array
    {
        $schema = $this->schema();
        if (false === $schema) {
            throw new ToolCallException('Knowledge Base is unavailable.');
        }

        return $schema;
    }

    private function firstTable(array $names): ?string
    {
        foreach ($names as $name) {
            if (\Schema::hasTable($name)) {
                return $name;
            }
        }

        return null;
    }

    private function firstColumn(array $columns, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }

        return null;
    }

    /** @return int[] */
    private function mailboxIds(): array
    {
        $user = $this->context->user();
        if (null === $user) {
            throw new \LogicException('Knowledge Base read attempted without an authenticated user.');
        }

        return array_map('intval', $user->mailboxesIdsCanView());
    }

    private function applyCursor($query, string $table, ?string $cursor): void
    {
        try {
            $id = PageCursor::decode($cursor);
        } catch (\InvalidArgumentException $exception) {
            throw new ToolCallException($exception->getMessage());
        }
        if (null !== $id) {
            $query->where($table.'.id', '<', $id);
        }
    }

    private function page($rows, int $limit, callable $serializer): array
    {
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map($serializer)->values()->all(),
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? PageCursor::encode((int) $rows->last()->id) : null,
        ];
    }

    private function serializeArticle($row, array $schema, bool $withBody): array
    {
        $article = [
            'id' => (int) $row->id,
            'mailbox_id' => (int) $row->{$schema['article_mailbox']},
            'title' => (string) $row->{$schema['article_title']},
        ];
        if (in_array('category_id', $schema['article_columns'], true)) {
            $article['category_id'] = null === $row->category_id ? null : (int) $row->category_id;
        }
        if ($withBody) {
            $article['body'] = mb_substr(trim(html_entity_decode(strip_tags((string) $row->{$schema['article_body']}), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 50000);
        }

        return $article;
    }

    private function serializeCategory($row, array $schema): array
    {
        $category = [
            'id' => (int) $row->id,
            'mailbox_id' => (int) $row->{$schema['category_mailbox']},
            'title' => (string) $row->{$schema['category_title']},
        ];
        if (in_array('parent_id', $schema['category_columns'], true)) {
            $category['parent_id'] = null === $row->parent_id ? null : (int) $row->parent_id;
        }

        return $category;
    }
}
