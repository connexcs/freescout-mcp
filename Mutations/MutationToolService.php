<?php

namespace Modules\McpServer\Mutations;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Contracts\MutationRepository;
use Modules\McpServer\Contracts\MutationRunner;

final class MutationToolService
{
    private $repository;
    private $executor;

    public function __construct(MutationRepository $repository, MutationRunner $executor)
    {
        $this->repository = $repository;
        $this->executor = $executor;
    }

    public function addNote(array $arguments): array
    {
        $tool = 'freescout_add_note';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            $id = $this->id($arguments, 'ticket_id');
            $body = $this->body($arguments);
            $key = $this->key($arguments);

            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, [
                'body_length' => mb_strlen($body),
            ], function () use ($id, $body) {
                return $this->repository->addNote($id, $body);
            });
        });
    }

    public function updateTicket(array $arguments): array
    {
        $tool = 'freescout_update_ticket';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            $id = $this->id($arguments, 'ticket_id');
            $key = $this->key($arguments);
            $status = null;
            if (isset($arguments['status'])) {
                if (!is_string($arguments['status']) || !in_array($arguments['status'], ['active', 'pending', 'closed', 'spam'], true)) {
                    throw new ToolCallException('Invalid status.');
                }
                $status = $arguments['status'];
            }
            $assignee = null;
            if (array_key_exists('assignee_id', $arguments)) {
                $assignee = filter_var($arguments['assignee_id'], FILTER_VALIDATE_INT);
                if (false === $assignee || 0 === $assignee || $assignee < -1) {
                    throw new ToolCallException('assignee_id must be -1 or a positive integer.');
                }
                $assignee = (int) $assignee;
            }
            if (null === $status && null === $assignee) {
                throw new ToolCallException('Provide status and/or assignee_id.');
            }

            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, [
                'status' => $status,
                'assignee_id' => $assignee,
            ], function () use ($id, $status, $assignee) {
                return $this->repository->updateTicket($id, $status, $assignee);
            });
        });
    }

    public function createDraftReply(array $arguments): array
    {
        $tool = 'freescout_create_draft_reply';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            $id = $this->id($arguments, 'ticket_id');
            $body = $this->body($arguments);
            $key = $this->key($arguments);
            $cc = $this->emails($arguments, 'cc');
            $bcc = $this->emails($arguments, 'bcc');

            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, [
                'body_length' => mb_strlen($body),
                'cc_count' => count($cc),
                'bcc_count' => count($bcc),
                'externally_visible' => false,
            ], function () use ($id, $body, $cc, $bcc) {
                return $this->repository->createDraftReply($id, $body, $cc, $bcc);
            });
        });
    }

    private function validated(string $tool, array $arguments, callable $operation): array
    {
        try {
            return $operation();
        } catch (ToolCallException $exception) {
            // Executor-owned failures have already produced an audit record.
            if (!in_array($exception->getMessage(), [
                'Ticket not found.', 'Status already set.', 'Assignee already set.',
                'Assignee is not available for this mailbox.', 'Ticket has no reply recipient.',
                'Idempotency key was already used with different arguments.',
                'An operation with this idempotency key is still in progress.',
            ], true)) {
                $target = filter_var($arguments['ticket_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $this->executor->validationFailure(
                    $tool,
                    'ticket',
                    false === $target ? null : (int) $target,
                    is_string($arguments['idempotency_key'] ?? null) ? $arguments['idempotency_key'] : null,
                    $this->validationMeta($arguments)
                );
            }
            throw $exception;
        }
    }

    private function id(array $arguments, string $name): int
    {
        $id = filter_var($arguments[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $id) {
            throw new ToolCallException($name.' must be a positive integer.');
        }

        return (int) $id;
    }

    private function body(array $arguments): string
    {
        $body = $arguments['body'] ?? null;
        if (!is_string($body) || '' === trim($body) || mb_strlen($body) > 100000) {
            throw new ToolCallException('body must be a non-empty string no longer than 100000 characters.');
        }

        return trim($body);
    }

    private function key(array $arguments): string
    {
        $key = $arguments['idempotency_key'] ?? null;
        if (!is_string($key)) {
            throw new ToolCallException('idempotency_key is required.');
        }

        return $key;
    }

    /** @return string[] */
    private function emails(array $arguments, string $name): array
    {
        $values = $arguments[$name] ?? [];
        if (!is_array($values) || count($values) > 50) {
            throw new ToolCallException($name.' must contain at most 50 email addresses.');
        }
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value) || false === filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 191) {
                throw new ToolCallException($name.' contains an invalid email address.');
            }
            $result[] = mb_strtolower($value);
        }

        return array_values(array_unique($result));
    }

    /** @return array<string, int|string|bool|null> */
    private function validationMeta(array $arguments): array
    {
        return [
            'has_body' => isset($arguments['body']),
            'body_length' => is_string($arguments['body'] ?? null) ? mb_strlen($arguments['body']) : 0,
            'has_status' => isset($arguments['status']),
            'has_assignee' => array_key_exists('assignee_id', $arguments),
        ];
    }
}
