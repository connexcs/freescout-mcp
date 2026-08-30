<?php

namespace Modules\McpServer\Security;

final class TokenCodec
{
    public const PREFIX = 'fsmcp';

    /** @var string */
    private $key;

    public function __construct(string $pepper)
    {
        $this->key = $this->normalizeKey($pepper);
    }

    /**
     * @return array{token: string, selector: string, secret_hash: string}
     */
    public function generate(): array
    {
        $selector = $this->base64UrlEncode(random_bytes(12));
        $secret = $this->base64UrlEncode(random_bytes(32));

        return [
            'token' => self::PREFIX.'_'.$selector.'_'.$secret,
            'selector' => $selector,
            'secret_hash' => $this->hashSecret($secret),
        ];
    }

    /**
     * @return array{selector: string, secret: string}|null
     */
    public function parse(string $token): ?array
    {
        if (!preg_match('/^'.self::PREFIX.'_([A-Za-z0-9_-]{16})_([A-Za-z0-9_-]{43})$/D', $token, $matches)) {
            return null;
        }

        return ['selector' => $matches[1], 'secret' => $matches[2]];
    }

    public function hashSecret(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->key);
    }

    public function verify(string $secret, string $expectedHash): bool
    {
        return 64 === strlen($expectedHash)
            && hash_equals($expectedHash, $this->hashSecret($secret));
    }

    public function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key);
    }

    private function normalizeKey(string $pepper): string
    {
        if (0 === strpos($pepper, 'base64:')) {
            $decoded = base64_decode(substr($pepper, 7), true);
            if (false !== $decoded) {
                $pepper = $decoded;
            }
        }

        return hash('sha256', $pepper, true);
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
