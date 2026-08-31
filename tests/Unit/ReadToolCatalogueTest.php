<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use Modules\McpServer\Services\McpServerFactory;
use Modules\McpServer\Tests\Support\FakeReadRepository;
use Modules\McpServer\Tools\ReadToolCatalogue;
use Modules\McpServer\Tools\ReadToolService;
use PHPUnit\Framework\TestCase;

final class ReadToolCatalogueTest extends TestCase
{
    public function testListsPermissionSafeReadOnlyTools(): void
    {
        $protocol = (new McpServerFactory([], new ReadToolCatalogue(new ReadToolService(new FakeReadRepository()))))->build();
        $body = $this->call($protocol, 'tools/list');
        $tools = $body['result']['tools'];

        self::assertCount(7, $tools);
        self::assertSame('freescout_get_ticket', $tools[0]['name']);
        foreach ($tools as $tool) {
            self::assertTrue($tool['annotations']['readOnlyHint']);
            self::assertFalse($tool['annotations']['destructiveHint']);
            self::assertFalse($tool['annotations']['openWorldHint']);
        }
    }

    public function testCallsToolAndReturnsStructuredContent(): void
    {
        $repository = new FakeReadRepository();
        $repository->tickets[7] = ['id' => 7, 'subject' => 'Allowed'];
        $protocol = (new McpServerFactory([], new ReadToolCatalogue(new ReadToolService($repository))))->build();
        $body = $this->call($protocol, 'tools/call', ['name' => 'freescout_get_ticket', 'arguments' => ['ticket_id' => 7]]);

        self::assertSame(7, $body['result']['structuredContent']['ticket']['id']);
        self::assertFalse($body['result']['isError']);
    }

    private function call($protocol, string $method, array $params = []): array
    {
        $params['_meta'] = [
            RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
            RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
        ];
        $result = $protocol->handle(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR), [
            'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
            'Mcp-Method' => $method,
            'Mcp-Name' => $params['name'] ?? null,
        ]);

        self::assertSame(200, $result->httpStatus, $result->toJson());

        return json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);
    }
}
