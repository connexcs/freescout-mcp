<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use Modules\McpServer\Services\McpServerFactory;
use PHPUnit\Framework\TestCase;

final class McpServerFactoryTest extends TestCase
{
    public function testDiscoveryAdvertisesOnlyModernToolsCapability(): void
    {
        $answer = $this->call('server/discover');

        self::assertSame(200, $answer['status']);
        self::assertSame(
            'freescout-mcp',
            $answer['body']['result']['_meta'][RequestMeta::SERVER_INFO]['name']
        );
        self::assertSame(['2026-07-28'], $answer['body']['result']['supportedVersions']);
        self::assertArrayHasKey('tools', $answer['body']['result']['capabilities']);
        self::assertArrayNotHasKey('resources', $answer['body']['result']['capabilities']);
        self::assertArrayNotHasKey('prompts', $answer['body']['result']['capabilities']);
        self::assertSame(60000, $answer['body']['result']['ttlMs']);
        self::assertSame('private', $answer['body']['result']['cacheScope']);
    }

    public function testFoundationHasAnEmptyDeterministicToolCatalogue(): void
    {
        $answer = $this->call('tools/list');

        self::assertSame(200, $answer['status']);
        self::assertSame([], $answer['body']['result']['tools']);
        self::assertSame(60000, $answer['body']['result']['ttlMs']);
        self::assertSame('private', $answer['body']['result']['cacheScope']);
    }

    public function testLegacyInitializeIsNotAccepted(): void
    {
        $answer = $this->call('initialize');

        self::assertNotSame(200, $answer['status']);
        self::assertArrayHasKey('error', $answer['body']);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function call(string $method): array
    {
        $protocol = (new McpServerFactory(['catalog_ttl_ms' => 60000]))->build();
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => [
                '_meta' => [
                    RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
                    RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $protocol->handle($payload, [
            'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
            'Mcp-Method' => $method,
        ]);

        return [
            'status' => $result->httpStatus,
            'body' => json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}
