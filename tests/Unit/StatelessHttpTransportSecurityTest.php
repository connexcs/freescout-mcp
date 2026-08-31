<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Schema\Wire\McpHeader;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StatelessHttpTransport;
use Modules\McpServer\Services\McpServerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class StatelessHttpTransportSecurityTest extends TestCase
{
    public function testRejectsOversizedRequestBeforeProtocolDispatch(): void
    {
        $factory = new Psr17Factory();
        $transport = new StatelessHttpTransport(
            (new McpServerFactory())->build(),
            $factory,
            $factory,
            new NullLogger(),
            1024,
            []
        );
        $request = $factory->createServerRequest('POST', 'https://support.example.test/mcp')
            ->withBody($factory->createStream(str_repeat('x', 1025)));

        $response = $transport->handle($request);

        self::assertSame(413, $response->getStatusCode());
        self::assertStringContainsString('maximum allowed size of 1024 bytes', (string) $response->getBody());
    }

    public function testCorsPreflightAllowsOnlyExplicitOriginAndHeaders(): void
    {
        $factory = new Psr17Factory();
        $transport = new StatelessHttpTransport(
            (new McpServerFactory())->build(),
            $factory,
            $factory,
            new NullLogger(),
            4096,
            [new CorsMiddleware(
                ['https://client.example.test'],
                ['POST', 'OPTIONS'],
                ['Accept', 'Authorization', 'Content-Type', McpHeader::PROTOCOL_VERSION, McpHeader::METHOD, McpHeader::NAME],
                []
            )]
        );
        $allowed = $factory->createServerRequest('OPTIONS', 'https://support.example.test/mcp')
            ->withHeader('Origin', 'https://client.example.test')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'authorization, content-type, mcp-protocol-version, mcp-method');
        $denied = $allowed->withHeader('Origin', 'https://evil.example.test');

        $allowedResponse = $transport->handle($allowed);
        $deniedResponse = $transport->handle($denied);

        self::assertSame(204, $allowedResponse->getStatusCode());
        self::assertSame('https://client.example.test', $allowedResponse->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertNotSame('https://evil.example.test', $deniedResponse->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
