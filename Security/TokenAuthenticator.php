<?php

namespace Modules\McpServer\Security;

use Modules\McpServer\Contracts\TokenRepository;

final class TokenAuthenticator
{
    /** @var TokenRepository */
    private $tokens;

    /** @var TokenCodec */
    private $codec;

    /** @var TokenPolicy */
    private $policy;

    public function __construct(TokenRepository $tokens, TokenCodec $codec, TokenPolicy $policy)
    {
        $this->tokens = $tokens;
        $this->codec = $codec;
        $this->policy = $policy;
    }

    public function authenticate(string $plainToken, ?string $ipAddress = null): ?AuthenticatedPrincipal
    {
        $parts = $this->codec->parse($plainToken);
        if (null === $parts) {
            return null;
        }

        $token = $this->tokens->findBySelector($parts['selector']);
        if (null === $token) {
            // Do equivalent keyed work for unknown selectors.
            $this->codec->verify($parts['secret'], str_repeat('0', 64));

            return null;
        }

        if (!$this->codec->verify($parts['secret'], (string) $token->secret_hash)
            || null !== $token->revoked_at
            || $this->isExpired($token->expires_at)
            || null === $token->user
            || !$this->policy->canAuthenticateUser($token->user)
        ) {
            return null;
        }

        $this->tokens->markUsed($token, $ipAddress);

        return new AuthenticatedPrincipal($token->user, $token);
    }

    /** @param mixed $expiresAt */
    private function isExpired($expiresAt): bool
    {
        if (null === $expiresAt) {
            return false;
        }

        if ($expiresAt instanceof \DateTimeInterface) {
            return $expiresAt->getTimestamp() <= time();
        }

        $timestamp = strtotime((string) $expiresAt);

        return false === $timestamp || $timestamp <= time();
    }
}
