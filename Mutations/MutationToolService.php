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

    public function tagsAvailable(): bool
    {
        return $this->repository->tagsAvailable();
    }

    public function addNote(array $arguments): array
    {
        $tool = 'freescout_add_note';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            $id = $this->id($arguments, 'ticket_id');
            $body = $this->body($arguments);
            $key = $this->key($arguments);
            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, ['body_length' => mb_strlen($body)], function () use ($id, $body) {
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
            $status = isset($arguments['status']) ? $this->status($arguments['status']) : null;
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
            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, ['status' => $status, 'assignee_id' => $assignee], function () use ($id, $status, $assignee) {
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
            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, ['body_length' => mb_strlen($body), 'cc_count' => count($cc), 'bcc_count' => count($bcc), 'externally_visible' => false], function () use ($id, $body, $cc, $bcc) {
                return $this->repository->createDraftReply($id, $body, $cc, $bcc);
            });
        });
    }

    public function sendReply(array $arguments): array
    {
        $tool = 'freescout_send_reply';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            if (($arguments['confirm_send'] ?? null) !== true) {
                throw new ToolCallException('confirm_send must be true for every customer-visible reply.');
            }
            $id = $this->id($arguments, 'ticket_id');
            $body = $this->body($arguments);
            $key = $this->key($arguments);
            $cc = $this->emails($arguments, 'cc');
            $bcc = $this->emails($arguments, 'bcc');
            $status = isset($arguments['status']) ? $this->customerVisibleStatus($arguments['status']) : null;
            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, ['body_length' => mb_strlen($body), 'cc_count' => count($cc), 'bcc_count' => count($bcc), 'status' => $status, 'externally_visible' => true, 'confirm_send' => true], function () use ($id, $body, $cc, $bcc, $status) {
                return $this->repository->sendReply($id, $body, $cc, $bcc, $status);
            });
        });
    }

    public function createTicket(array $arguments): array
    {
        $tool = 'freescout_create_ticket';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            if (($arguments['confirm_send'] ?? null) !== true) {
                throw new ToolCallException('confirm_send must be true because creating a ticket schedules the initial customer-visible message for delivery.');
            }
            $mailboxId = $this->id($arguments, 'mailbox_id');
            $subject = $this->shortString($arguments, 'subject', 998);
            $body = $this->body($arguments);
            $key = $this->key($arguments);
            $customerId = isset($arguments['customer_id']) ? $this->id($arguments, 'customer_id') : null;
            $customerEmail = isset($arguments['customer_email']) ? $this->email($arguments['customer_email'], 'customer_email') : null;
            if (null === $customerId && null === $customerEmail) {
                throw new ToolCallException('Provide customer_id or customer_email.');
            }
            $assignee = null;
            if (array_key_exists('assignee_id', $arguments)) {
                $assignee = filter_var($arguments['assignee_id'], FILTER_VALIDATE_INT);
                if (false === $assignee || 0 === $assignee || $assignee < -1) {
                    throw new ToolCallException('assignee_id must be -1 or a positive integer.');
                }
                $assignee = (int) $assignee;
            }
            $status = isset($arguments['status']) ? $this->customerVisibleStatus($arguments['status']) : null;
            return $this->executor->execute($tool, 'mailbox', $mailboxId, $key, $arguments, ['body_length' => mb_strlen($body), 'subject_length' => mb_strlen($subject), 'has_customer_id' => null !== $customerId, 'has_customer_email' => null !== $customerEmail, 'assignee_id' => $assignee, 'status' => $status, 'externally_visible' => true, 'confirm_send' => true], function () use ($mailboxId, $subject, $customerId, $customerEmail, $body, $assignee, $status) {
                return $this->repository->createTicket($mailboxId, $subject, $customerId, $customerEmail, $body, $assignee, $status);
            });
        }, 'mailbox_id', 'mailbox');
    }

    public function setTicketTags(array $arguments): array
    {
        $tool = 'freescout_set_ticket_tags';
        return $this->validated($tool, $arguments, function () use ($tool, $arguments) {
            $id = $this->id($arguments, 'ticket_id');
            $key = $this->key($arguments);
            $tags = $arguments['tags'] ?? null;
            if (!is_array($tags) || count($tags) > 50) {
                throw new ToolCallException('tags must be an array containing at most 50 tag names.');
            }
            $normalized = [];
            foreach ($tags as $tag) {
                if (!is_string($tag) || '' === trim($tag) || mb_strlen($tag) > 191) {
                    throw new ToolCallException('tags contains an invalid tag name.');
                }
                $normalized[] = trim($tag);
            }
            $normalized = array_values(array_unique($normalized));
            return $this->executor->execute($tool, 'ticket', $id, $key, $arguments, ['tag_count' => count($normalized)], function () use ($id, $normalized) {
                return $this->repository->setTicketTags($id, $normalized);
            });
        });
    }

    private function validated(string $tool, array $arguments, callable $operation, string $targetField = 'ticket_id', string $targetType = 'ticket'): array
    {
        try {
            return $operation();
        } catch (ToolCallException $exception) {
            $message = $exception->getMessage();
            $executorOwned = in_array($message, [
                'Ticket not found.', 'Mailbox not found.', 'Customer not found.', 'Customer email does not belong to customer.',
                'Status already set.', 'Assignee already set.', 'Assignee is not available for this mailbox.',
                'Ticket has no reply recipient.', 'Tags module is unavailable.',
                'Idempotency key was already used with different arguments.', 'An operation with this idempotency key is still in progress.',
            ], true) || 0 === strpos($message, 'Unknown tag: ');
            if (!$executorOwned) {
                $target = filter_var($arguments[$targetField] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $this->executor->validationFailure($tool, $targetType, false === $target ? null : (int) $target, is_string($arguments['idempotency_key'] ?? null) ? $arguments['idempotency_key'] : null, $this->validationMeta($arguments));
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
        return $this->shortString($arguments, 'body', 100000);
    }

    private function shortString(array $arguments, string $name, int $max): string
    {
        $value = $arguments[$name] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $max) {
            throw new ToolCallException($name.' must be a non-empty string no longer than '.$max.' characters.');
        }
        return trim($value);
    }

    private function key(array $arguments): string
    {
        $key = $arguments['idempotency_key'] ?? null;
        if (!is_string($key)) {
            throw new ToolCallException('idempotency_key is required.');
        }
        return $key;
    }

    private function status($value): string
    {
        if (!is_string($value) || !in_array($value, ['active', 'pending', 'closed', 'spam'], true)) {
            throw new ToolCallException('Invalid status.');
        }
        return $value;
    }

    private function customerVisibleStatus($value): string
    {
        if (!is_string($value) || !in_array($value, ['active', 'pending', 'closed'], true)) {
            throw new ToolCallException('Invalid status.');
        }
        return $value;
    }

    private function email($value, string $name): string
    {
        if (!is_string($value) || false === filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 191) {
            throw new ToolCallException($name.' must be a valid email address.');
        }
        return mb_strtolower($value);
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
            $result[] = $this->email($value, $name);
        }
        return array_values(array_unique($result));
    }

    private function validationMeta(array $arguments): array
    {
        return [
            'has_body' => isset($arguments['body']),
            'body_length' => is_string($arguments['body'] ?? null) ? mb_strlen($arguments['body']) : 0,
            'has_status' => isset($arguments['status']),
            'has_assignee' => array_key_exists('assignee_id', $arguments),
            'has_customer_id' => isset($arguments['customer_id']),
            'has_customer_email' => isset($arguments['customer_email']),
            'tag_count' => is_array($arguments['tags'] ?? null) ? count($arguments['tags']) : 0,
            'confirm_send' => ($arguments['confirm_send'] ?? null) === true,
        ];
    }
}
