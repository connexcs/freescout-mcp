<?php

namespace Modules\McpServer\KnowledgeBase;

use Mcp\Exception\ToolCallException;

final class KnowledgeBaseToolService
{
    private $repository;

    public function __construct(KnowledgeBaseRepository $repository)
    {
        $this->repository = $repository;
    }

    public function available(): bool
    {
        return $this->repository->available();
    }

    public function getArticle(array $arguments): array
    {
        $article = $this->repository->article($this->id($arguments, 'article_id'));
        if (null === $article) {
            throw new ToolCallException('Knowledge Base article not found.');
        }

        return ['article' => $article];
    }

    public function searchArticles(array $arguments): array
    {
        return $this->repository->searchArticles($this->query($arguments), $this->limit($arguments), $this->cursor($arguments));
    }

    public function getCategory(array $arguments): array
    {
        $category = $this->repository->category($this->id($arguments, 'category_id'));
        if (null === $category) {
            throw new ToolCallException('Knowledge Base category not found.');
        }

        return ['category' => $category];
    }

    public function searchCategories(array $arguments): array
    {
        return $this->repository->searchCategories($this->query($arguments), $this->limit($arguments), $this->cursor($arguments));
    }

    private function id(array $arguments, string $name): int
    {
        $id = filter_var($arguments[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $id) {
            throw new ToolCallException($name.' must be a positive integer.');
        }

        return (int) $id;
    }

    private function query(array $arguments): string
    {
        $query = $arguments['query'] ?? null;
        if (!is_string($query) || '' === trim($query) || mb_strlen($query) > 200) {
            throw new ToolCallException('query must be a non-empty string no longer than 200 characters.');
        }

        return trim($query);
    }

    private function limit(array $arguments): int
    {
        $limit = $arguments['limit'] ?? 25;
        $limit = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (false === $limit) {
            throw new ToolCallException('limit must be between 1 and 100.');
        }

        return (int) $limit;
    }

    private function cursor(array $arguments): ?string
    {
        $cursor = $arguments['cursor'] ?? null;
        if (null !== $cursor && (!is_string($cursor) || strlen($cursor) > 128)) {
            throw new ToolCallException('Invalid pagination cursor.');
        }

        return $cursor;
    }
}
