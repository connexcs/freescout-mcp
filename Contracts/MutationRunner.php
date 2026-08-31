<?php

namespace Modules\McpServer\Contracts;

interface MutationRunner
{
    /** @return array<string, mixed> */
    public function execute(string $tool, string $targetType, int $targetId, string $key, array $requestData, array $safeMeta, callable $operation): array;

    public function validationFailure(string $tool, string $targetType, ?int $targetId, ?string $key, array $safeMeta): void;
}
