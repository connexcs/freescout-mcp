<?php

namespace Modules\McpServer\Mutations;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Entities\McpIdempotency;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Security\TokenCodec;
use Modules\McpServer\Contracts\MutationRunner;

final class MutationExecutor implements MutationRunner
{
    private $context;
    private $audit;
    private $codec;

    public function __construct(McpRequestContext $context, AuditLogger $audit, TokenCodec $codec)
    {
        $this->context = $context;
        $this->audit = $audit;
        $this->codec = $codec;
    }

    /**
     * @param array<string, mixed> $requestData
     * @param array<string, int|string|bool|null> $safeMeta
     * @param callable(): array<string, mixed> $operation
     * @return array<string, mixed>
     */
    public function execute(string $tool, string $targetType, int $targetId, string $key, array $requestData, array $safeMeta, callable $operation): array
    {
        $this->validateKey($key);
        $principal = $this->context->principal();
        if (null === $principal) {
            throw new \LogicException('Mutation attempted without an authenticated principal.');
        }
        if (!$principal->hasScope('mcp:write')) {
            $this->recordFailure($tool, $targetType, $targetId, 'denied', $key, $safeMeta, 'insufficient_scope');
            throw new ToolCallException('The OAuth token does not grant mcp:write.');
        }

        $tokenId = $principal->auditTokenId();
        $hash = $this->codec->fingerprint(json_encode($this->canonicalize($requestData), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        try {
            return \DB::transaction(function () use ($tool, $targetType, $targetId, $key, $safeMeta, $operation, $tokenId, $hash) {
                $record = McpIdempotency::where('token_id', $tokenId)
                    ->where('tool', $tool)
                    ->where('idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if (null !== $record) {
                    if (!hash_equals((string) $record->request_hash, $hash)) {
                        throw new ToolCallException('Idempotency key was already used with different arguments.');
                    }
                    if (null === $record->response_json) {
                        throw new ToolCallException('An operation with this idempotency key is still in progress.');
                    }
                    $response = json_decode($record->response_json, true, 512, JSON_THROW_ON_ERROR);
                    $response['replayed'] = true;
                    $this->audit->record($tool, $targetType, $targetId, 'replayed', $key, $safeMeta);

                    return $response;
                }

                $record = McpIdempotency::create([
                    'token_id' => $tokenId,
                    'tool' => $tool,
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                ]);

                $response = $operation();
                $response['replayed'] = false;
                $record->response_json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $record->completed_at = \Carbon\Carbon::now();
                $record->save();
                $this->audit->record($tool, $targetType, $targetId, 'succeeded', $key, $safeMeta);

                return $response;
            }, 3);
        } catch (ToolCallException $exception) {
            $code = str_starts_with($exception->getMessage(), 'Ticket not found') ? 'not_found_or_denied' : 'rejected';
            $this->recordFailure($tool, $targetType, $targetId, 'denied', $key, $safeMeta, $code);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->recordFailure($tool, $targetType, $targetId, 'failed', $key, $safeMeta, 'internal_error');
            throw $exception;
        }
    }

    /** @param array<string, int|string|bool|null> $safeMeta */
    public function validationFailure(string $tool, string $targetType, ?int $targetId, ?string $key, array $safeMeta): void
    {
        $this->recordFailure($tool, $targetType, $targetId, 'validation_failed', $key, $safeMeta, 'invalid_arguments');
    }

    /** @param array<string, int|string|bool|null> $safeMeta */
    private function recordFailure(string $tool, string $targetType, ?int $targetId, string $outcome, ?string $key, array $safeMeta, string $error): void
    {
        try {
            $this->audit->record($tool, $targetType, $targetId, $outcome, $key, $safeMeta, $error);
        } catch (\Throwable $ignored) {
            // The original mutation error must never be hidden by audit storage failure.
        }
    }

    private function validateKey(string $key): void
    {
        if (1 !== preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{7,127}\z/', $key)) {
            throw new ToolCallException('idempotency_key must contain 8-128 safe characters.');
        }
    }

    /** @return mixed */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
