<?php

namespace Modules\McpServer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mcp\Schema\Wire\McpHeader;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StatelessHttpTransport;
use Modules\McpServer\Http\LaravelResponseFactory;
use Modules\McpServer\Http\Psr7RequestFactory;
use Modules\McpServer\Services\McpServerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

final class McpController
{
    private $servers;
    private $requests;
    private $responses;

    public function __construct(
        McpServerFactory $servers,
        Psr7RequestFactory $requests,
        LaravelResponseFactory $responses
    ) {
        $this->servers = $servers;
        $this->requests = $requests;
        $this->responses = $responses;
    }

    public function handle(Request $request): Response
    {
        if (!config('mcpserver.enabled', false)) {
            return new Response('MCP Server is disabled.', 503, ['Content-Type' => 'text/plain']);
        }

        $factory = new Psr17Factory();
        $transport = new StatelessHttpTransport(
            $this->servers->build(),
            $factory,
            $factory,
            new \Psr\Log\NullLogger(),
            max(1024, (int) config('mcpserver.max_body_bytes', 1024 * 1024)),
            [
                new CorsMiddleware(
                    (array) config('mcpserver.allowed_origins', []),
                    ['POST', 'OPTIONS'],
                    ['Accept', 'Authorization', 'Content-Type', McpHeader::PROTOCOL_VERSION, McpHeader::METHOD, McpHeader::NAME],
                    []
                ),
                new DnsRebindingProtectionMiddleware(
                    (array) config('mcpserver.allowed_hosts', ['localhost']),
                    $factory,
                    $factory
                ),
            ]
        );

        return $this->responses->create($transport->handle($this->requests->create($request)));
    }
}
