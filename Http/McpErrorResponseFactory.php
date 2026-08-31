<?php

namespace Modules\McpServer\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class McpErrorResponseFactory
{
    public function unauthorized(Request $request, bool $invalidToken = true): Response
    {
        $challenge = 'Bearer realm="FreeScout MCP"';
        if ($invalidToken) {
            $challenge .= ', error="invalid_token"';
        }
        if (filter_var(config('mcpserver.oauth.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $challenge .= ', resource_metadata="'.rtrim((string) config('app.url'), '/').'/.well-known/oauth-protected-resource", scope="mcp:read"';
            } catch (\Throwable $ignored) {
                // Route discovery may not be available in isolated unit tests.
            }
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
