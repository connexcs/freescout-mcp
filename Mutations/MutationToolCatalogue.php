<?php

namespace Modules\McpServer\Mutations;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Modules\McpServer\Tools\CallbackToolHandler;
use Modules\McpServer\Security\McpRequestContext;

final class MutationToolCatalogue
{
    private $tools;
    private $policy;
    private $context;

    public function __construct(MutationToolService $tools, MutationPolicy $policy, ?McpRequestContext $context = null)
    {
        $this->tools = $tools;
        $this->policy = $policy;
        $this->context = $context;
    }

    public function enabled(): bool
    {
        $principal = null === $this->context ? null : $this->context->principal();
        return $this->policy->enabled() && (null === $principal || $principal->hasScope('mcp:write'));
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
            ], 'required' => ['ticket_id', 'idempotency_key'], 'anyOf' => [['required' => ['status']], ['required' => ['assignee_id']]], 'additionalProperties' => false,
        ], true);

        $this->add($builder, 'freescout_create_draft_reply', 'Create draft reply', 'Create, but never send, a draft reply on an accessible ticket. No customer-visible action occurs.', [$this->tools, 'createDraftReply'], [
            'type' => 'object', 'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100000, 'description' => 'Plain-text draft body.'],
                'cc' => $this->emailList(), 'bcc' => $this->emailList(), 'idempotency_key' => $idempotency,
            ], 'required' => ['ticket_id', 'body', 'idempotency_key'], 'additionalProperties' => false,
        ], false);

        $this->add($builder, 'freescout_send_reply', 'Send reply', 'Schedule a customer-visible reply on an accessible ticket. confirm_send=true is mandatory on every call.', [$this->tools, 'sendReply'], [
            'type' => 'object', 'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100000, 'description' => 'Plain-text customer-visible reply.'],
                'cc' => $this->emailList(), 'bcc' => $this->emailList(),
                'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'closed']],
                'confirm_send' => ['type' => 'boolean', 'const' => true, 'description' => 'Explicit confirmation that this call may send email to the customer.'],
                'idempotency_key' => $idempotency,
            ], 'required' => ['ticket_id', 'body', 'confirm_send', 'idempotency_key'], 'additionalProperties' => false,
        ], true, false);

        $this->add($builder, 'freescout_create_ticket', 'Create ticket', 'Create a new email ticket and schedule its initial customer-visible message. confirm_send=true is mandatory.', [$this->tools, 'createTicket'], [
            'type' => 'object', 'properties' => [
                'mailbox_id' => ['type' => 'integer', 'minimum' => 1],
                'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 998],
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'customer_email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100000],
                'assignee_id' => ['type' => 'integer', 'minimum' => -1, 'not' => ['const' => 0]],
                'status' => ['type' => 'string', 'enum' => ['active', 'pending', 'closed']],
                'confirm_send' => ['type' => 'boolean', 'const' => true],
                'idempotency_key' => $idempotency,
            ], 'required' => ['mailbox_id', 'subject', 'body', 'confirm_send', 'idempotency_key'],
            'anyOf' => [['required' => ['customer_id']], ['required' => ['customer_email']]], 'additionalProperties' => false,
        ], true, false);

        if ($this->tools->tagsAvailable()) {
            $this->add($builder, 'freescout_set_ticket_tags', 'Set ticket tags', 'Replace the complete tag set on an accessible ticket using existing tag names only. Unknown names are rejected.', [$this->tools, 'setTicketTags'], [
                'type' => 'object', 'properties' => [
                    'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                    'tags' => ['type' => 'array', 'maxItems' => 50, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191]],
                    'idempotency_key' => $idempotency,
                ], 'required' => ['ticket_id', 'tags', 'idempotency_key'], 'additionalProperties' => false,
            ], true);
        }
    }

    private function add(Builder $builder, string $name, string $title, string $description, callable $handler, array $schema, bool $destructive, bool $idempotent = true): void
    {
        $builder->add(
            new Tool($name, $title, $schema, $description, new ToolAnnotations(null, false, $destructive, $idempotent, false), outputSchema: ['type' => 'object', 'additionalProperties' => true]),
            new CallbackToolHandler($handler)
        );
    }

    private function emailList(): array
    {
        return ['type' => 'array', 'maxItems' => 50, 'items' => ['type' => 'string', 'format' => 'email', 'maxLength' => 191]];
    }
}
