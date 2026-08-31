<?php

namespace Modules\McpServer\Mutations;

use Modules\McpServer\Entities\McpAuditLog;
use Modules\McpServer\Security\McpRequestContext;

final class AuditLogger
{
    private $context;

    public function __construct(McpRequestContext $context)
    {
        $this->context = $context;
    }

    /** @param array<string, int|string|bool|null> $meta */
    public function record(string $tool, string $targetType, ?int $targetId, string $outcome, ?string $key, array $meta, ?string $errorCode = null): void
    {
        $principal = $this->context->principal();
        McpAuditLog::create([
            'user_id' => null === $principal ? null : (int) $principal->user->id,
            'token_id' => null === $principal ? null : $principal->auditTokenId(),
            'tool' => $tool,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'outcome' => $outcome,
            'idempotency_key' => $key,
            'argument_meta' => $meta,
            'error_code' => $errorCode,
            'created_at' => \Carbon\Carbon::now(),
        ]);
    }
}
