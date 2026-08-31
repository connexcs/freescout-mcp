<?php

namespace Modules\McpServer\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\McpServer\OAuth\OAuthServerConfiguration;

final class McpErrorResponseFactory
{
    private $oauth;

    public function __construct(OAuthServerConfiguration $oauth)
    {
        $this->oauth = $oauth;
    }

    public function unauthorized(Request $request, bool $invalidToken = true): Response
    {
        $challenge = 'Bearer realm="FreeScout MCP"';
        if ($invalidToken) {
            $challenge .= ', error="invalid_token"';
        }
        if ($this->oauth->enabled()) {
            $challenge .= ', resource_metadata="'.$this->oauth->protectedResourceMetadataUrl().'", scope="mcp:read"';
        }

        return $this->jsonRpc($request, 401, -32001, 'Unauthorized', [
            'WWW-Authenticate' => $challenge,
        ]);
    }

    public function tooManyRequests(Request $request, int $retryAfter): Response
    {
        return $this->jsonRpc($request, 429, -32002, 'Too many requests', [
            'Retry-After' => (string) max(1, $retryAfter),
        ]);
    }

    public function unavailable(Request $request): Response
    {
        return $this->jsonRpc($request, 503, -32003, 'MCP Server is disabled');
    }

    /** @param array<string, string> $headers */
    private function jsonRpc(Request $request, int $status, int $code, string $message, array $headers = []): Response
    {
        $response = new Response(json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_THROW_ON_ERROR), $status, array_merge([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ], $headers));

        $origin = $request->headers->get('Origin');
        if (is_string($origin) && in_array($origin, (array) config('mcpserver.allowed_origins', []), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
