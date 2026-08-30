<?php

namespace Modules\McpServer\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Modules\McpServer\Http\McpErrorResponseFactory;
use Modules\McpServer\Security\McpRequestContext;

final class ThrottleMcpRequests
{
    private $limiter;
    private $context;
    private $errors;

    public function __construct(RateLimiter $limiter, McpRequestContext $context, McpErrorResponseFactory $errors)
    {
        $this->limiter = $limiter;
        $this->context = $context;
        $this->errors = $errors;
    }

    public function handle(Request $request, Closure $next)
    {
        if ('OPTIONS' === $request->getMethod() || !config('mcpserver.enabled', false)) {
            return $next($request);
        }

        $token = $this->context->token();
        if (null === $token) {
            return $this->errors->unauthorized($request);
        }

        $key = 'mcpserver:requests:'.(int) $token->id;
        $maxAttempts = max(1, (int) config('mcpserver.authenticated_rate_limit', 120));

        if ($this->limiter->tooManyAttempts($key, $maxAttempts, 1)) {
            return $this->errors->tooManyRequests($request, $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, 1);
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) max(0, $this->limiter->retriesLeft($key, $maxAttempts))
        );

        return $response;
    }
}
