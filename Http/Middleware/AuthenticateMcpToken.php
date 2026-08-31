<?php

namespace Modules\McpServer\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Modules\McpServer\Http\McpErrorResponseFactory;
use Modules\McpServer\Security\BearerTokenParser;
use Modules\McpServer\Security\McpRequestContext;
use Modules\McpServer\Security\TokenAuthenticator;
use Modules\McpServer\Security\TokenCodec;
use Modules\McpServer\OAuth\OAuthServerConfiguration;
use Modules\McpServer\OAuth\OAuthTokenService;

final class AuthenticateMcpToken
{
    private $authenticator;
    private $parser;
    private $context;
    private $limiter;
    private $errors;
    private $codec;
    private $oauth;
    private $oauthConfiguration;

    public function __construct(
        TokenAuthenticator $authenticator,
        BearerTokenParser $parser,
        McpRequestContext $context,
        RateLimiter $limiter,
        McpErrorResponseFactory $errors,
        TokenCodec $codec,
        OAuthTokenService $oauth,
        OAuthServerConfiguration $oauthConfiguration
    ) {
        $this->authenticator = $authenticator;
        $this->parser = $parser;
        $this->context = $context;
        $this->limiter = $limiter;
        $this->errors = $errors;
        $this->codec = $codec;
        $this->oauth = $oauth;
        $this->oauthConfiguration = $oauthConfiguration;
    }

    public function handle(Request $request, Closure $next)
    {
        $this->context->clear();

        if ('OPTIONS' === $request->getMethod() || !config('mcpserver.enabled', false)) {
            return $next($request);
        }

        $failureKey = 'mcpserver:auth:'.$this->codec->fingerprint((string) $request->ip());
        $maxAttempts = max(1, (int) config('mcpserver.unauthenticated_rate_limit', 30));

        if ($this->limiter->tooManyAttempts($failureKey, $maxAttempts, 1)) {
            return $this->errors->tooManyRequests($request, $this->limiter->availableIn($failureKey));
        }

        $plainToken = $this->parser->parse($request->headers->get('Authorization'));
        if (null === $plainToken) {
            $this->limiter->hit($failureKey, 1);

            return $this->errors->unauthorized($request, false);
        }

        $principal = 0 === strpos($plainToken, 'fsmcp_oa_') && $this->oauthConfiguration->enabled()
            ? $this->oauth->authenticateAccess($plainToken, $request->ip(), $this->oauthConfiguration->resource())
            : $this->authenticator->authenticate($plainToken, $request->ip());
        if (null === $principal) {
            $this->limiter->hit($failureKey, 1);

            return $this->errors->unauthorized($request, true);
        }

        $this->context->set($principal);
        $request->attributes->set('mcp_user', $principal->user);
        $request->attributes->set('mcp_token', $principal->token);
        \Auth::setUser($principal->user);

        return $next($request);
    }
}
