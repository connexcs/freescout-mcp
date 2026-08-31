<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Exception\ToolCallException;
use Mcp\Server\ClientGateway;
use Modules\McpServer\Tools\CallbackToolHandler;
use PHPUnit\Framework\TestCase;

final class CallbackToolHandlerTest extends TestCase
{
    public function testRejectsOversizedToolOutputWithoutReturningItsContents(): void
    {
        $handler = new CallbackToolHandler(static fn () => ['body' => str_repeat('customer-data-', 90000)]);

        try {
            $handler->execute([], $this->createMock(ClientGateway::class));
            self::fail('Oversized output was accepted.');
        } catch (ToolCallException $exception) {
            self::assertSame('Tool output exceeds the configured response limit. Reduce the requested page size.', $exception->getMessage());
            self::assertStringNotContainsString('customer-data', $exception->getMessage());
        }
    }

    public function testReturnsOutputWithinTheLimit(): void
    {
        $result = ['items' => [['id' => 1]]];
        $handler = new CallbackToolHandler(static fn () => $result);

        self::assertSame($result, $handler->execute([], $this->createMock(ClientGateway::class)));
    }
}
