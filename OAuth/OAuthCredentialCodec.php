<?php

namespace Modules\McpServer\OAuth;

use Modules\McpServer\Security\TokenCodec;

final class OAuthCredentialCodec
{
    private $codec;

    public function __construct(TokenCodec $codec)
    {
        $this->codec = $codec;
    }

    /** @return array{plain: string, selector: string, secret_hash: string} */
    public function generate(string $type): array
    {
        if (!in_array($type, ['code', 'access', 'refresh'], true)) {
            throw new \InvalidArgumentException('Unsupported OAuth credential type.');
        }

        $selector = $this->encode(random_bytes(12));
        $secret = $this->encode(random_bytes(32));

        return [
            'plain' => $this->prefix($type).'_'.$selector.'_'.$secret,
            'selector' => $selector,
            'secret_hash' => $this->codec->hashSecret($secret),
        ];
    }

    /** @return array{selector: string, secret: string}|null */
    public function parse(string $value, string $type): ?array
    {
        $prefix = preg_quote($this->prefix($type), '/');
        if (1 !== preg_match('/^'.$prefix.'_([A-Za-z0-9_-]{16})_([A-Za-z0-9_-]{43})$/D', $value, $matches)) {
            return null;
        }

        return ['selector' => $matches[1], 'secret' => $matches[2]];
    }

    public function verify(string $secret, string $expected): bool
    {
        return $this->codec->verify($secret, $expected);
    }

    private function prefix(string $type): string
    {
        return ['code' => 'fsmcp_oc', 'access' => 'fsmcp_oa', 'refresh' => 'fsmcp_or'][$type] ?? 'invalid';
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
