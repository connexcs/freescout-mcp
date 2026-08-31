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
