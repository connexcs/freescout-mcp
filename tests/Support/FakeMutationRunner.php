<?php

namespace Modules\McpServer\Tests\Support;

use Modules\McpServer\Contracts\MutationRunner;

final class FakeMutationRunner implements MutationRunner
{
    public $executions = [];
    public $validationFailures = [];

    public function execute(string $tool, string $targetType, int $targetId, string $key, array $requestData, array $safeMeta, callable $operation): array
    {
        $this->executions[] = compact('tool', 'targetType', 'targetId', 'key', 'safeMeta');
        $result = $operation();
        $result['replayed'] = false;
        return $result;
    }

    public function validationFailure(string $tool, string $targetType, ?int $targetId, ?string $key, array $safeMeta): void
    {
        $this->validationFailures[] = compact('tool', 'targetType', 'targetId', 'key', 'safeMeta');
    }
}
