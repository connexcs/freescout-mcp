<?php

namespace Modules\McpServer\OAuth;

use Carbon\Carbon;
use Modules\McpServer\Entities\McpOAuthClient;
use Modules\McpServer\Entities\McpOAuthCode;
use Modules\McpServer\Entities\McpOAuthToken;
use Modules\McpServer\Security\AuthenticatedPrincipal;
use Modules\McpServer\Security\TokenPolicy;

final class OAuthTokenService
{
    private $codec;
    private $policy;

    public function __construct(OAuthCredentialCodec $codec, TokenPolicy $policy)
    {
        $this->codec = $codec;
        $this->policy = $policy;
    }

    /** @param object $user @param string[] $scopes */
    public function issueCode($user, McpOAuthClient $client, string $redirectUri, string $resource, array $scopes, string $challenge): string
    {
        $issued = $this->codec->generate('code');
        McpOAuthCode::create([
            'user_id' => (int) $user->id,
            'client_record_id' => (int) $client->id,
            'selector' => $issued['selector'],
            'secret_hash' => $issued['secret_hash'],
            'redirect_uri' => $redirectUri,
            'resource' => $resource,
            'scopes' => implode(' ', $scopes),
            'code_challenge' => $challenge,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return $issued['plain'];
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $plain, string $clientId, string $redirectUri, string $resource, string $verifier): array
    {
        $parts = $this->codec->parse($plain, 'code');
        if (null === $parts || 1 !== preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/', $verifier)) {
            throw new OAuthException('invalid_grant', 'The authorization code or PKCE verifier is invalid.');
        }

        return \DB::transaction(function () use ($parts, $clientId, $redirectUri, $resource, $verifier) {
            $code = McpOAuthCode::where('selector', $parts['selector'])->lockForUpdate()->first();
            if (null === $code || !$this->codec->verify($parts['secret'], (string) $code->secret_hash)
                || null !== $code->consumed_at || $this->expired($code->expires_at)
                || null === $code->client || !hash_equals((string) $code->client->client_id, $clientId)
                || !hash_equals((string) $code->redirect_uri, $redirectUri)
                || !hash_equals((string) $code->resource, $resource)
                || !hash_equals((string) $code->code_challenge, $this->challenge($verifier))
            ) {
                throw new OAuthException('invalid_grant', 'The authorization code is invalid, expired, or already used.');
            }
            if (null === $code->user || !$this->policy->canAuthenticateOAuthUser($code->user)) {
                throw new OAuthException('invalid_grant', 'The FreeScout account is no longer eligible.');
            }

            $code->consumed_at = Carbon::now();
            $code->save();

            return $this->issuePair($code->user, $code->client, (string) $code->resource, $this->scopes((string) $code->scopes));
        }, 3);
    }

    /** @return array<string, mixed> */
    public function refresh(string $plain, string $clientId, string $resource, ?string $requestedScope = null): array
    {
        $parts = $this->codec->parse($plain, 'refresh');
        if (null === $parts) {
            throw new OAuthException('invalid_grant', 'The refresh token is invalid.');
        }

        $reuseDetected = false;
        $result = \DB::transaction(function () use ($parts, $clientId, $resource, $requestedScope, &$reuseDetected) {
            $token = McpOAuthToken::where('selector', $parts['selector'])->where('type', 'refresh')->lockForUpdate()->first();
            $verified = null !== $token && $this->codec->verify($parts['secret'], (string) $token->secret_hash);
            if ($verified && null !== $token->rotated_at) {
                McpOAuthToken::where('family_id', $token->family_id)->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);
                $reuseDetected = true;

                return null;
            }
            if (!$verified || null !== $token->revoked_at || $this->expired($token->expires_at)
                || null === $token->client || !hash_equals((string) $token->client->client_id, $clientId)
                || !hash_equals((string) $token->resource, $resource)
                || null === $token->user || !$this->policy->canAuthenticateOAuthUser($token->user)
            ) {
                throw new OAuthException('invalid_grant', 'The refresh token is invalid or expired.');
            }

            $scopes = $this->scopes((string) $token->scopes);
            if (null !== $requestedScope && '' !== trim($requestedScope)) {
                $requested = $this->validateScopes($requestedScope);
                if ([] !== array_diff($requested, $scopes)) {
                    throw new OAuthException('invalid_scope', 'A refresh request cannot increase its original scope.');
                }
                $scopes = $requested;
            }
            $token->rotated_at = Carbon::now();
            $token->revoked_at = Carbon::now();
            $token->save();

            return $this->issuePair($token->user, $token->client, (string) $token->resource, $scopes, (string) $token->family_id);
        }, 3);
        if ($reuseDetected) {
            throw new OAuthException('invalid_grant', 'Refresh token reuse was detected; the token family was revoked.');
        }

        return $result;
    }

    public function authenticateAccess(string $plain, ?string $ipAddress, string $resource): ?AuthenticatedPrincipal
    {
        $parts = $this->codec->parse($plain, 'access');
        if (null === $parts) {
            return null;
        }
        $token = McpOAuthToken::where('selector', $parts['selector'])->where('type', 'access')->first();
        if (null === $token || !$this->codec->verify($parts['secret'], (string) $token->secret_hash)
            || null !== $token->revoked_at || $this->expired($token->expires_at)
            || !hash_equals((string) $token->resource, $resource)
            || null === $token->user || !$this->policy->canAuthenticateOAuthUser($token->user)
        ) {
            return null;
        }
        $token->last_used_at = Carbon::now();
        $token->last_used_ip = $ipAddress;
        $token->save();

        return new AuthenticatedPrincipal($token->user, $token, 'oauth', $this->scopes((string) $token->scopes));
    }

    public function revoke(string $plain): void
    {
        foreach (['access', 'refresh'] as $type) {
            $parts = $this->codec->parse($plain, $type);
            if (null === $parts) {
                continue;
            }
            \DB::transaction(function () use ($parts, $type) {
                $token = McpOAuthToken::where('selector', $parts['selector'])->where('type', $type)->lockForUpdate()->first();
                if (null === $token || !$this->codec->verify($parts['secret'], (string) $token->secret_hash)) {
                    return;
                }
                if ('refresh' === $type) {
                    McpOAuthToken::where('family_id', $token->family_id)->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);
                } elseif (null === $token->revoked_at) {
                    $token->revoked_at = Carbon::now();
                    $token->save();
                }
            }, 3);

            return;
        }
    }

    /** @return string[] */
    public function validateScopes(?string $scope): array
    {
        $scopes = null === $scope || '' === trim($scope) ? ['mcp:read'] : preg_split('/\s+/', trim($scope));
        $scopes = array_values(array_unique(is_array($scopes) ? $scopes : []));
        if ([] !== array_diff($scopes, ['mcp:read', 'mcp:write'])) {
            throw new OAuthException('invalid_scope', 'Supported scopes are mcp:read and mcp:write.');
        }
        if (in_array('mcp:write', $scopes, true) && !in_array('mcp:read', $scopes, true)) {
            array_unshift($scopes, 'mcp:read');
        }

        return $scopes;
    }

    /** @param object $user @param string[] $scopes @return array<string, mixed> */
    private function issuePair($user, McpOAuthClient $client, string $resource, array $scopes, ?string $family = null): array
    {
        $access = $this->codec->generate('access');
        $refresh = $this->codec->generate('refresh');
        $family = $family ?? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $accessLifetime = max(300, min(86400, (int) config('mcpserver.oauth.access_token_lifetime_seconds', 3600)));
        $refreshLifetime = max(86400, min(31536000, (int) config('mcpserver.oauth.refresh_token_lifetime_seconds', 2592000)));
        $common = [
            'user_id' => (int) $user->id,
            'client_record_id' => (int) $client->id,
            'family_id' => $family,
            'resource' => $resource,
            'scopes' => implode(' ', $scopes),
        ];
        McpOAuthToken::create(array_merge($common, [
            'type' => 'access', 'selector' => $access['selector'], 'secret_hash' => $access['secret_hash'],
            'expires_at' => Carbon::now()->addSeconds($accessLifetime),
        ]));
        McpOAuthToken::create(array_merge($common, [
            'type' => 'refresh', 'selector' => $refresh['selector'], 'secret_hash' => $refresh['secret_hash'],
            'expires_at' => Carbon::now()->addSeconds($refreshLifetime),
        ]));

        return [
            'access_token' => $access['plain'], 'token_type' => 'Bearer', 'expires_in' => $accessLifetime,
            'refresh_token' => $refresh['plain'], 'scope' => implode(' ', $scopes),
        ];
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** @return string[] */
    private function scopes(string $value): array
    {
        return array_values(array_filter(explode(' ', $value), 'strlen'));
    }

    /** @param mixed $value */
    private function expired($value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp() <= time();
        }

        $timestamp = strtotime((string) $value);

        return false === $timestamp || $timestamp <= time();
    }
}
