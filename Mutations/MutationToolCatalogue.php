<?php

namespace Modules\McpServer\Mutations;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Modules\McpServer\Tools\CallbackToolHandler;

final class MutationToolCatalogue
{
    private $tools;
    private $policy;

    public function __construct(MutationToolService $tools, MutationPolicy $policy)
    {
        $this->tools = $tools;
        $this->policy = $policy;
    }

    public function enabled(): bool
    {
        return $this->policy->enabled();
    }

    public function register(Builder $builder): void
    {
        $idempotency = [
            'type' => 'string', 'minLength' => 8, 'maxLength' => 128,
            'pattern' => '^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$',
            'description' => 'Caller-generated key reused only when retrying this exact operation.',
        ];

        $this->add($builder, 'freescout_add_note', 'Add internal note', 'Add an internal note to an accessible ticket. This does not send a customer reply.', [$this->tools, 'addNote'], [
            'type' => 'object', 'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100000, 'description' => 'Plain-text internal note.'],
                'idempotency_key' => $idempotency,
            ], 'required' => ['ticket_id', 'body', 'idempotency_key'], 'additionalProperties' => false,
        ], false);

        $this->add($builder, 'freescout_update_ticket', 'Update ticket', 'Change an accessible ticket status and/or assignee using FreeScout domain behavior. Use -1 to unassign.', [$this->tools, 'updateTicket'], [
            'type' => 'object', 'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'closed', 'spam']],
                'assignee_id' => ['type' => 'integer', 'minimum' => -1, 'not' => ['const' => 0]],
                'idempotency_key' => $idempotency,
            ], 'required' => ['ticket_id', 'idempotency_key'],
            'anyOf' => [['required' => ['status']], ['required' => ['assignee_id']]],
            'additionalProperties' => false,
        ], true);

        $this->add($builder, 'freescout_create_draft_reply', 'Create draft reply', 'Create, but never send, a draft reply on an accessible ticket. No customer-visible action occurs.', [$this->tools, 'createDraftReply'], [
            'type' => 'object', 'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100000, 'description' => 'Plain-text draft body.'],
                'cc' => $this->emailList(), 'bcc' => $this->emailList(),
                'idempotency_key' => $idempotency,
            ], 'required' => ['ticket_id', 'body', 'idempotency_key'], 'additionalProperties' => false,
        ], false);
    }

    private function add(Builder $builder, string $name, string $title, string $description, callable $handler, array $schema, bool $destructive): void
    {
        $builder->add(
            new Tool($name, $title, $schema, $description, new ToolAnnotations(null, false, $destructive, true, false), outputSchema: ['type' => 'object', 'additionalProperties' => true]),
            new CallbackToolHandler($handler)
        );
    }

    private function emailList(): array
    {
        return ['type' => 'array', 'maxItems' => 50, 'items' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191]];
    }
}
