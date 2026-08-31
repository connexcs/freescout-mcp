<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Exception\ToolCallException;
use Modules\McpServer\Tests\Support\FakeReadRepository;
use Modules\McpServer\Tools\ReadToolService;
use PHPUnit\Framework\TestCase;

final class ReadToolServiceTest extends TestCase
{
    public function testInaccessibleAndMissingTicketsAreIndistinguishable(): void
    {
        $tools = new ReadToolService(new FakeReadRepository());

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Ticket not found.');
        $tools->getTicket(['ticket_id' => 999]);
    }

    public function testContextIncludesOnlyRepositoryAuthorizedData(): void
    {
        $repository = new FakeReadRepository();
        $repository->tickets[7] = ['id' => 7, 'subject' => 'Allowed'];
        $result = (new ReadToolService($repository))->getTicketContext(['ticket_id' => 7]);

        self::assertSame(7, $result['ticket']['id']);
        self::assertSame('Hello', $result['threads'][0]['body']);
    }

    public function testRejectsUnknownStatusAndOversizedLimit(): void
    {
        $tools = new ReadToolService(new FakeReadRepository());

        try {
            $tools->searchTickets(['status' => 'deleted']);
            self::fail('Expected invalid status failure.');
        } catch (ToolCallException $exception) {
            self::assertSame('Invalid status.', $exception->getMessage());
        }

        $this->expectException(ToolCallException::class);
        $tools->getMailboxes(['limit' => 101]);
    }
}
