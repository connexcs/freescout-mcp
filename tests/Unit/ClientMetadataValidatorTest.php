<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\OAuth\ClientMetadataValidator;
use Modules\McpServer\OAuth\OAuthException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientMetadataValidatorTest extends TestCase
{
    public function testAcceptsHttpsAndLoopbackRedirectsAndRequiresExactMatch(): void
    {
        $validator = new ClientMetadataValidator();
        $metadata = $validator->validate([
            'client_id' => 'https://client.example/mcp.json',
            'client_name' => 'Example',
            'redirect_uris' => ['https://client.example/callback', 'http://127.0.0.1:48123/callback'],
            'token_endpoint_auth_method' => 'none',
        ], 'https://client.example/mcp.json');

        $validator->assertExactRedirect($metadata, 'https://client.example/callback');
        $this->expectException(OAuthException::class);
        $validator->assertExactRedirect($metadata, 'https://client.example/callback/');
    }

    #[DataProvider('unsafeRedirects')]
    public function testRejectsUnsafeRedirects(string $redirect): void
    {
        $this->expectException(OAuthException::class);
        (new ClientMetadataValidator())->validate([
            'client_id' => 'client', 'client_name' => 'Bad', 'redirect_uris' => [$redirect],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function unsafeRedirects(): array
    {
        return [
            'plain remote HTTP' => ['http://example.com/callback'],
            'fragment' => ['https://example.com/callback#token'],
            'userinfo' => ['https://user@example.com/callback'],
            'custom scheme' => ['claude://oauth/callback'],
        ];
    }

    public function testRejectsMismatchedMetadataDocumentIdentity(): void
    {
        $this->expectException(OAuthException::class);
        (new ClientMetadataValidator())->validate([
            'client_id' => 'https://attacker.example/client.json',
            'client_name' => 'Mismatch',
            'redirect_uris' => ['https://client.example/callback'],
        ], 'https://client.example/client.json');
    }
}
