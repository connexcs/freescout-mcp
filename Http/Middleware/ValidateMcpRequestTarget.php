<?php

namespace Modules\McpServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\McpServer\Http\McpErrorResponseFactory;
use Modules\McpServer\Security\RequestTargetPolicy;

final class ValidateMcpRequestTarget
{
    private $policy;
    private $errors;

    public function __construct(RequestTargetPolicy $policy, McpErrorResponseFactory $errors)
    {
        $this->policy = $policy;
        $this->errors = $errors;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->policy->allowsHost((string) $request->headers->get('Host'))
            || !$this->policy->allowsOrigin($request->headers->get('Origin'))
        ) {
            return $this->errors->forbidden($request, 'Request host or origin is not allowed.');
        }

        return $next($request);
    }
}
