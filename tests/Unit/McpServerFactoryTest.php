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

    public function testMalformedAndMissingMetadataRequestsFailClosed(): void
    {
        $protocol = (new McpServerFactory())->build();
        $malformed = $protocol->handle('{');
        $missingMeta = $protocol->handle(json_encode([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(400, $malformed->httpStatus);
        self::assertSame(400, $missingMeta->httpStatus);
        self::assertStringContainsString('params._meta', $missingMeta->toJson());
    }

    public function testContradictoryProtocolAndMethodHeadersFailClosed(): void
    {
        $protocol = (new McpServerFactory())->build();
        $payload = json_encode([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => ['_meta' => [
                RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
                RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
            ]],
        ], JSON_THROW_ON_ERROR);
        $wrongVersion = $protocol->handle($payload, [
            'MCP-Protocol-Version' => '2025-11-25', 'Mcp-Method' => 'tools/list',
        ]);
        $wrongMethod = $protocol->handle($payload, [
            'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value, 'Mcp-Method' => 'tools/call',
        ]);

        self::assertSame(400, $wrongVersion->httpStatus);
        self::assertSame(400, $wrongMethod->httpStatus);
        self::assertStringContainsString('contradicts', $wrongVersion->toJson());
        self::assertStringContainsString('does not match', $wrongMethod->toJson());
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
