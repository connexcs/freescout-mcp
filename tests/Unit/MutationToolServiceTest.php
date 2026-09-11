<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Mutations\MutationToolService;
use Modules\McpServer\Tests\Support\FakeMutationRepository;
use Modules\McpServer\Tests\Support\FakeMutationRunner;
use PHPUnit\Framework\TestCase;

final class MutationToolServiceTest extends TestCase
{
    public function testCreatesPlainDraftWithoutSendSemantics(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $result = (new MutationToolService($repository, $runner))->createDraftReply([
            'ticket_id' => 7, 'body' => 'Draft only', 'cc' => ['COPY@EXAMPLE.TEST'], 'idempotency_key' => 'draft-key-0001',
        ]);

        self::assertFalse($result['sent']);
        self::assertSame('copy@example.test', $repository->calls[0][3][0]);
        self::assertSame(10, $runner->executions[0]['safeMeta']['body_length']);
    }

    public function testSendReplyRequiresExplicitConfirmation(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $tools = new MutationToolService($repository, $runner);

        try {
            $tools->sendReply(['ticket_id' => 7, 'body' => 'Visible reply', 'idempotency_key' => 'send-key-0001']);
            self::fail('Expected confirmation failure.');
        } catch (ToolCallException $exception) {
            self::assertSame('confirm_send must be true for every customer-visible reply.', $exception->getMessage());
        }
        self::assertSame([], $repository->calls);
        self::assertCount(1, $runner->validationFailures);
    }

    public function testConfirmedReplyNormalizesRecipientsAndUsesIdempotencyRunner(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $result = (new MutationToolService($repository, $runner))->sendReply([
            'ticket_id' => 7,
            'body' => 'Visible reply',
            'cc' => ['COPY@EXAMPLE.TEST'],
            'confirm_send' => true,
            'idempotency_key' => 'send-key-0002',
        ]);

        self::assertFalse($result['sent']);
        self::assertSame('scheduled', $result['delivery_state']);
        self::assertSame('send', $repository->calls[0][0]);
        self::assertSame('copy@example.test', $repository->calls[0][3][0]);
        self::assertTrue($runner->executions[0]['safeMeta']['externally_visible']);
    }

    public function testCreateTicketRequiresExplicitConfirmation(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $tools = new MutationToolService($repository, $runner);

        $this->expectException(ToolCallException::class);
        try {
            $tools->createTicket([
                'mailbox_id' => 1,
                'subject' => 'Test',
                'customer_email' => 'customer@example.test',
                'body' => 'Initial message',
                'idempotency_key' => 'create-key-001',
            ]);
        } finally {
            self::assertSame([], $repository->calls);
        }
    }

    public function testSetsTicketTagsWithReplaceSemantics(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $result = (new MutationToolService($repository, $runner))->setTicketTags([
            'ticket_id' => 7,
            'tags' => ['priority', 'vip', 'priority'],
            'idempotency_key' => 'tags-key-0001',
        ]);

        self::assertSame(['priority', 'vip'], $repository->calls[0][2]);
        self::assertCount(2, $result['tags']);
    }

    public function testUpdateRequiresAChangeAndAuditsValidation(): void
    {
        $runner = new FakeMutationRunner();
        $tools = new MutationToolService(new FakeMutationRepository(), $runner);

        try {
            $tools->updateTicket(['ticket_id' => 7, 'idempotency_key' => 'update-key-001']);
            self::fail('Expected validation failure.');
        } catch (ToolCallException $exception) {
            self::assertSame('Provide status and/or assignee_id.', $exception->getMessage());
        }
        self::assertCount(1, $runner->validationFailures);
        self::assertSame('freescout_update_ticket', $runner->validationFailures[0]['tool']);
    }

    public function testRejectsInvalidRecipientsWithoutCallingRepository(): void
    {
        $repository = new FakeMutationRepository();
        $runner = new FakeMutationRunner();
        $tools = new MutationToolService($repository, $runner);

        $this->expectException(ToolCallException::class);
        try {
            $tools->createDraftReply(['ticket_id' => 7, 'body' => 'x', 'cc' => ['bad'], 'idempotency_key' => 'draft-key-0002']);
        } finally {
            self::assertSame([], $repository->calls);
        }
    }
}
